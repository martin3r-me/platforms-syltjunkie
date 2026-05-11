<?php

namespace Platform\Syltjunkie\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Platform\Core\Http\Controllers\ApiController;
use Platform\Syltjunkie\Http\Controllers\Api\Concerns\ResolvesPublicTeam;
use Platform\Syltjunkie\Http\Middleware\SjUserAuthenticate;
use Platform\Syltjunkie\Models\SjUser;
use Platform\Syltjunkie\Services\SjMailService;

class UserAuthController extends ApiController
{
    use ResolvesPublicTeam;

    public function requestLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:255',
            'redirect_url' => 'required|url|max:500',
            'from_address' => 'required|email|max:255',
        ]);

        $teamId = $this->resolveTeamId($request);
        $email = strtolower($validated['email']);
        $redirectUrl = $validated['redirect_url'];
        $fromAddress = $validated['from_address'];

        $rateLimitKey = 'sj-user-magic-link:' . $teamId . ':' . $email;
        $maxAttempts = config('syltjunkie.user_auth.rate_limit_per_hour', 5);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            return $this->success(null, 'Wir haben deine Anfrage erhalten.');
        }

        RateLimiter::hit($rateLimitKey, 3600);

        $user = SjUser::firstOrCreate(
            ['team_id' => $teamId, 'email' => $email],
            ['name' => $validated['name'] ?? null, 'status' => 'active']
        );

        // Blocked user → silent response (no info leak)
        if ($user->status === 'blocked') {
            return $this->success(null, 'Wir haben deine Anfrage erhalten.');
        }

        // Update name if provided and user had none
        if (!empty($validated['name']) && !$user->name) {
            $user->update(['name' => $validated['name']]);
        }

        $token = $user->generateToken();
        SjMailService::sendUserMagicLink($user, $token, $fromAddress, $redirectUrl);

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

        $user = SjUser::where('team_id', $teamId)
            ->where('email', $email)
            ->active()
            ->first();

        if (!$user || !$user->isTokenValid($validated['token'])) {
            return $this->error('Ungültiger oder abgelaufener Link.', null, 401);
        }

        $user->update(['last_login_at' => now()]);
        $user->clearToken();

        $bearerToken = SjUserAuthenticate::generateBearerToken($email, $teamId);

        return $this->success([
            'token' => $bearerToken,
            'expires_in' => config('syltjunkie.user_auth.session_ttl_hours', 24) * 3600,
        ]);
    }
}
