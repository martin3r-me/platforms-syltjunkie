<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
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
