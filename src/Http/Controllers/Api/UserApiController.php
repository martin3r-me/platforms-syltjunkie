<?php

namespace Platform\Syltjunkie\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Platform\Core\Http\Controllers\ApiController;
use Platform\Syltjunkie\Models\SjUser;

class UserApiController extends ApiController
{
    public function pointsHistory(Request $request): JsonResponse
    {
        $email = $request->attributes->get('sj_user_email');
        $teamId = $request->attributes->get('sj_user_team_id');

        $user = SjUser::where('team_id', $teamId)
            ->where('email', $email)
            ->active()
            ->first();

        if (! $user) {
            return $this->error('User not found', 404);
        }

        $history = $user->pointsHistory()
            ->select(['action', 'points', 'meta', 'created_at'])
            ->paginate(20);

        return $this->success($history);
    }

    public function me(Request $request): JsonResponse
    {
        $email = $request->attributes->get('sj_user_email');
        $teamId = $request->attributes->get('sj_user_team_id');

        $user = SjUser::where('team_id', $teamId)
            ->where('email', $email)
            ->active()
            ->first();

        return $this->success([
            'email' => $email,
            'name' => $user?->name,
            'last_login_at' => $user?->last_login_at?->toIso8601String(),
            'points_balance' => $user?->points_balance ?? 0,
            'current_level' => $user?->currentLevel(),
            'next_level' => $user?->nextLevel(),
        ]);
    }
}
