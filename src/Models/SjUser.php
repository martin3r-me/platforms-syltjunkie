<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Platform\Core\Models\Team;

class SjUser extends Model
{
    protected $table = 'sj_users';

    protected $fillable = [
        'team_id',
        'email',
        'name',
        'status',
        'token',
        'token_expires_at',
        'last_login_at',
        'points_balance',
        'current_level',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'last_login_at' => 'datetime',
        'points_balance' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function pointsHistory(): HasMany
    {
        return $this->hasMany(SjUserPoint::class)->orderByDesc('created_at');
    }

    public function currentLevel(): array
    {
        $levels = config('syltjunkie.gamification.levels', []);

        foreach ($levels as $level) {
            if ($level['key'] === $this->current_level) {
                return $level;
            }
        }

        return $levels[0] ?? ['key' => 'tagesgast', 'name' => 'Tagesgast', 'min_points' => 0];
    }

    public function nextLevel(): ?array
    {
        $levels = config('syltjunkie.gamification.levels', []);
        $found = false;

        foreach ($levels as $level) {
            if ($found) {
                return $level;
            }
            if ($level['key'] === $this->current_level) {
                $found = true;
            }
        }

        return null;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeBlocked($query)
    {
        return $query->where('status', 'blocked');
    }

    public function generateToken(): string
    {
        $token = Str::random(64);
        $expiresAt = now()->addMinutes(config('syltjunkie.user_auth.token_ttl_minutes', 30));

        $this->update([
            'token' => $token,
            'token_expires_at' => $expiresAt,
        ]);

        return $token;
    }

    public function isTokenValid(string $token): bool
    {
        return $this->token === $token
            && $this->token_expires_at
            && $this->token_expires_at->isFuture();
    }

    public function clearToken(): void
    {
        $this->update([
            'token' => null,
            'token_expires_at' => null,
        ]);
    }
}
