<?php

namespace Platform\Syltjunkie\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Platform\Core\Http\Controllers\ApiController;
use Platform\Syltjunkie\Http\Controllers\Api\Concerns\ResolvesPublicTeam;
use Platform\Syltjunkie\Http\Middleware\SjOwnerAuthenticate;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Services\SjMailService;
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
            'redirect_url' => 'required|url|max:500',
            'from_address' => 'required|email|max:255',
        ]);

        $teamId = $this->resolveTeamId($request);
        $email = strtolower($validated['email']);
        $redirectUrl = $validated['redirect_url'];
        $fromAddress = $validated['from_address'];

        $rateLimitKey = 'sj-magic-link:' . $teamId . ':' . $email;
        $maxAttempts = config('syltjunkie.owner_auth.rate_limit_per_hour', 3);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            return $this->success(null, 'Wir haben deine Anfrage erhalten.');
        }

        RateLimiter::hit($rateLimitKey, 3600);

        // Entity per Slug auflösen, falls mitgegeben
        $entityId = null;
        if (!empty($validated['entity_slug'])) {
            $entityId = SjEntity::where('team_id', $teamId)
                ->where('slug', $validated['entity_slug'])
                ->value('id');

            // Ungültiger Slug → stille Antwort
            if (!$entityId) {
                return $this->success(null, 'Wir haben deine Anfrage erhalten.');
            }
        }

        // Prüfen ob diese E-Mail bereits approved Einträge hat
        $hasApproved = SjEntityOwner::where('team_id', $teamId)
            ->where('email', $email)
            ->approved()
            ->exists();

        if ($hasApproved) {
            // Bereits freigegebener User → Magic Link senden
            $owner = SjEntityOwner::where('team_id', $teamId)
                ->where('email', $email)
                ->approved()
                ->first();

            // from_address + redirect_url auf allen Einträgen dieser E-Mail aktualisieren
            SjEntityOwner::where('team_id', $teamId)
                ->where('email', $email)
                ->update([
                    'from_address' => $fromAddress,
                    'redirect_url' => $redirectUrl,
                ]);
            $owner->from_address = $fromAddress;
            $owner->redirect_url = $redirectUrl;

            $token = $owner->generateToken();
            SjMailService::sendMagicLink($owner, $token);

            return $this->success(null, 'Wir haben deine Anfrage erhalten.');
        }

        // Kein entity_slug und keine existierenden Entities → verwerfen
        if (!$entityId) {
            return $this->success(null, 'Wir haben deine Anfrage erhalten.');
        }

        // Entity bereits von anderem Owner beansprucht?
        $entityAlreadyClaimed = SjEntityOwner::where('team_id', $teamId)
            ->where('entity_id', $entityId)
            ->approved()
            ->exists();

        if ($entityAlreadyClaimed) {
            return $this->success(null, 'Wir haben deine Anfrage erhalten.');
        }

        // Prüfen ob schon ein pending Eintrag für diese Kombination existiert
        $existingPending = SjEntityOwner::where('team_id', $teamId)
            ->where('email', $email)
            ->where('entity_id', $entityId)
            ->exists();

        if (!$existingPending) {
            SjEntityOwner::create([
                'team_id' => $teamId,
                'email' => $email,
                'name' => $validated['name'] ?? null,
                'entity_id' => $entityId,
                'status' => 'pending',
                'from_address' => $fromAddress,
                'redirect_url' => $redirectUrl,
            ]);
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
        $email = strtolower($validated['email']);

        // Irgendeinen approved Eintrag mit gültigem Token finden
        $owner = SjEntityOwner::where('team_id', $teamId)
            ->where('email', $email)
            ->approved()
            ->first();

        if (!$owner || !$owner->isTokenValid($validated['token'])) {
            return $this->error('Ungültiger oder abgelaufener Link.', null, 401);
        }

        // last_login_at auf allen Einträgen setzen
        SjEntityOwner::where('team_id', $teamId)
            ->where('email', $email)
            ->approved()
            ->update(['last_login_at' => now()]);

        $owner->clearToken();

        $bearerToken = SjOwnerAuthenticate::generateBearerToken($email, $teamId);

        return $this->success([
            'token' => $bearerToken,
            'expires_in' => config('syltjunkie.owner_auth.session_ttl_hours', 24) * 3600,
        ]);
    }
}
