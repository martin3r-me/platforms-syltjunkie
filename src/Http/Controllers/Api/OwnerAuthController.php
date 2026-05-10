<?php

namespace Platform\Syltjunkie\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Platform\Core\Http\Controllers\ApiController;
use Platform\Syltjunkie\Http\Controllers\Api\Concerns\ResolvesPublicTeam;
use Platform\Syltjunkie\Http\Middleware\SjOwnerAuthenticate;
use Platform\Syltjunkie\Mail\SjMagicLinkMail;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityOwner;

class OwnerAuthController extends ApiController
{
    use ResolvesPublicTeam;

    public function requestLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:255',
            'entity_slug' => 'nullable|string|max:255',
            'redirect_url' => 'nullable|url|max:500',
        ]);

        $teamId = $this->resolveTeamId($request);
        $email = strtolower($validated['email']);

        $rateLimitKey = 'sj-magic-link:' . $teamId . ':' . $email;
        $maxAttempts = config('syltjunkie.owner_auth.rate_limit_per_hour', 3);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            return $this->success(null, 'Wir haben deine Anfrage erhalten.');
        }

        RateLimiter::hit($rateLimitKey, 3600);

        // Entity per Slug aufloesen, falls mitgegeben
        $entityId = null;
        if (!empty($validated['entity_slug'])) {
            $entityId = SjEntity::where('team_id', $teamId)
                ->where('slug', $validated['entity_slug'])
                ->value('id');
        }

        $owner = SjEntityOwner::where('team_id', $teamId)
            ->where('email', $email)
            ->first();

        if (!$owner) {
            // Nur anlegen wenn Entity nicht schon einen approved Owner hat
            $entityAlreadyClaimed = $entityId && SjEntityOwner::where('team_id', $teamId)
                ->where('entity_id', $entityId)
                ->approved()
                ->exists();

            if (!$entityAlreadyClaimed) {
                SjEntityOwner::create([
                    'team_id' => $teamId,
                    'email' => $email,
                    'name' => $validated['name'] ?? null,
                    'entity_id' => $entityId,
                    'status' => 'pending',
                ]);
            }
        } elseif ($owner->status === 'approved') {
            // Bereits freigeschaltet: Magic Link senden
            $token = $owner->generateToken();
            $redirectUrl = $validated['redirect_url'] ?? null;
            Mail::to($owner->email)->send(new SjMagicLinkMail($owner, $token, $redirectUrl));
        }

        // Immer gleiche Antwort, kein Info-Leak
        return $this->success(null, 'Wir haben deine Anfrage erhalten.');
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'token' => 'required|string|size:64',
        ]);

        $teamId = $this->resolveTeamId($request);

        $owner = SjEntityOwner::where('team_id', $teamId)
            ->where('email', strtolower($validated['email']))
            ->approved()
            ->first();

        if (!$owner || !$owner->isTokenValid($validated['token'])) {
            return $this->error('Ungültiger oder abgelaufener Link.', null, 401);
        }

        $owner->update(['last_login_at' => now()]);
        $owner->clearToken();

        $bearerToken = SjOwnerAuthenticate::generateBearerToken($owner);

        return $this->success([
            'token' => $bearerToken,
            'expires_in' => config('syltjunkie.owner_auth.session_ttl_hours', 24) * 3600,
        ]);
    }
}
