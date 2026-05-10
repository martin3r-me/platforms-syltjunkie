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
use Platform\Syltjunkie\Models\SjEntityOwner;

class OwnerAuthController extends ApiController
{
    use ResolvesPublicTeam;

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:255',
        ]);

        $teamId = $this->resolveTeamId($request);

        SjEntityOwner::firstOrCreate(
            ['team_id' => $teamId, 'email' => strtolower($validated['email'])],
            ['name' => $validated['name'] ?? null, 'status' => 'pending']
        );

        return $this->success(null, 'Anfrage erhalten. Du wirst benachrichtigt, sobald dein Zugang freigeschaltet wird.');
    }

    public function requestLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $teamId = $this->resolveTeamId($request);
        $email = strtolower($validated['email']);

        $rateLimitKey = 'sj-magic-link:' . $teamId . ':' . $email;
        $maxAttempts = config('syltjunkie.owner_auth.rate_limit_per_hour', 3);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            return $this->success(null, 'Falls ein Konto mit dieser E-Mail existiert, wurde ein Login-Link gesendet.');
        }

        RateLimiter::hit($rateLimitKey, 3600);

        $owner = SjEntityOwner::where('team_id', $teamId)
            ->where('email', $email)
            ->approved()
            ->first();

        if ($owner) {
            $token = $owner->generateToken();
            Mail::to($owner->email)->send(new SjMagicLinkMail($owner, $token));
        }

        // Generic response regardless of whether owner exists
        return $this->success(null, 'Falls ein Konto mit dieser E-Mail existiert, wurde ein Login-Link gesendet.');
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
