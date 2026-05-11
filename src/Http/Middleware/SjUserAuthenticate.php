<?php

namespace Platform\Syltjunkie\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Platform\Syltjunkie\Models\SjUser;
use Symfony\Component\HttpFoundation\Response;

class SjUserAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (!$bearer) {
            return response()->json(['success' => false, 'message' => 'Nicht autorisiert'], 401);
        }

        $payload = $this->decodeToken($bearer);

        if (!$payload) {
            return response()->json(['success' => false, 'message' => 'Ungültiges Token'], 401);
        }

        if (($payload['type'] ?? null) !== 'user') {
            return response()->json(['success' => false, 'message' => 'Ungültiges Token'], 401);
        }

        if (now()->timestamp > $payload['expires_at']) {
            return response()->json(['success' => false, 'message' => 'Token abgelaufen'], 401);
        }

        $userExists = SjUser::where('team_id', $payload['team_id'])
            ->where('email', $payload['email'])
            ->active()
            ->exists();

        if (!$userExists) {
            return response()->json(['success' => false, 'message' => 'Nicht autorisiert'], 401);
        }

        $request->attributes->set('sj_user_email', $payload['email']);
        $request->attributes->set('sj_user_team_id', $payload['team_id']);

        return $next($request);
    }

    protected function decodeToken(string $bearer): ?array
    {
        $parts = explode('.', $bearer, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$payloadBase64, $signature] = $parts;

        $expectedSignature = hash_hmac('sha256', $payloadBase64, config('app.key'));

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payload = json_decode(base64_decode($payloadBase64), true);

        if (!$payload || !isset($payload['email'], $payload['team_id'], $payload['expires_at'])) {
            return null;
        }

        return $payload;
    }

    public static function generateBearerToken(string $email, int $teamId): string
    {
        $payload = [
            'type' => 'user',
            'email' => $email,
            'team_id' => $teamId,
            'expires_at' => now()->addHours(
                config('syltjunkie.user_auth.session_ttl_hours', 24)
            )->timestamp,
        ];

        $payloadBase64 = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $payloadBase64, config('app.key'));

        return $payloadBase64 . '.' . $signature;
    }
}
