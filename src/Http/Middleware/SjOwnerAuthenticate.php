<?php

namespace Platform\Syltjunkie\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Platform\Syltjunkie\Models\SjEntityOwner;
use Symfony\Component\HttpFoundation\Response;

class SjOwnerAuthenticate
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

        if (now()->timestamp > $payload['expires_at']) {
            return response()->json(['success' => false, 'message' => 'Token abgelaufen'], 401);
        }

        $owner = SjEntityOwner::where('id', $payload['owner_id'])
            ->where('team_id', $payload['team_id'])
            ->approved()
            ->first();

        if (!$owner || $owner->entity_id !== $payload['entity_id']) {
            return response()->json(['success' => false, 'message' => 'Nicht autorisiert'], 401);
        }

        $request->attributes->set('sj_owner', $owner);

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

        if (!$payload || !isset($payload['owner_id'], $payload['entity_id'], $payload['team_id'], $payload['expires_at'])) {
            return null;
        }

        return $payload;
    }

    public static function generateBearerToken(SjEntityOwner $owner): string
    {
        $payload = [
            'owner_id' => $owner->id,
            'entity_id' => $owner->entity_id,
            'team_id' => $owner->team_id,
            'expires_at' => now()->addHours(
                config('syltjunkie.owner_auth.session_ttl_hours', 24)
            )->timestamp,
        ];

        $payloadBase64 = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $payloadBase64, config('app.key'));

        return $payloadBase64 . '.' . $signature;
    }
}
