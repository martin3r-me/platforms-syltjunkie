<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;

class SjEntityOwner extends Model
{
    protected $table = 'sj_entity_owners';

    protected $fillable = [
        'team_id',
        'entity_id',
        'email',
        'name',
        'status',
        'token',
        'token_expires_at',
        'last_login_at',
        'approved_at',
        'approved_by',
        'notes',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'last_login_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(SjEntity::class, 'entity_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeBlocked($query)
    {
        return $query->where('status', 'blocked');
    }

    public function generateToken(): string
    {
        $token = Str::random(64);
        $this->update([
            'token' => $token,
            'token_expires_at' => now()->addMinutes(
                config('syltjunkie.owner_auth.token_ttl_minutes', 30)
            ),
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
