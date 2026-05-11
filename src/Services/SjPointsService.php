<?php

namespace Platform\Syltjunkie\Services;

use Platform\Syltjunkie\Models\SjUser;
use Platform\Syltjunkie\Models\SjUserPoint;

class SjPointsService
{
    /**
     * Punkte vergeben. Erstellt Ledger-Eintrag, aktualisiert Balance + Level.
     */
    public static function award(SjUser $user, string $action, int $points, array $meta = []): SjUserPoint
    {
        $entry = SjUserPoint::create([
            'team_id' => $user->team_id,
            'sj_user_id' => $user->id,
            'action' => $action,
            'points' => $points,
            'meta' => $meta ?: null,
        ]);

        $user->increment('points_balance', $points);

        $newBalance = $user->fresh()->points_balance;
        $newLevel = self::resolveLevel($newBalance);

        if ($newLevel !== $user->current_level) {
            $user->update(['current_level' => $newLevel]);
        }

        return $entry;
    }

    /**
     * Level aus Config anhand der aktuellen Punktzahl berechnen.
     */
    public static function resolveLevel(int $points): string
    {
        $levels = config('syltjunkie.gamification.levels', []);
        $resolved = 'tagesgast';

        foreach ($levels as $level) {
            if ($points >= $level['min_points']) {
                $resolved = $level['key'];
            }
        }

        return $resolved;
    }

    /**
     * Balance aus Ledger neu berechnen (Repair/Sync).
     */
    public static function recalculateBalance(SjUser $user): void
    {
        $sum = $user->pointsHistory()->sum('points');

        $user->update([
            'points_balance' => max(0, $sum),
            'current_level' => self::resolveLevel(max(0, $sum)),
        ]);
    }
}
