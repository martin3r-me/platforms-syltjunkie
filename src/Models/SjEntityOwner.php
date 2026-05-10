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
        'from_address',
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

    /**
     * Generate a magic link token for this owner (sets on all rows for same email+team).
     */
    public function generateToken(): string
    {
        $token = Str::random(64);
        $expiresAt = now()->addMinutes(config('syltjunkie.owner_auth.token_ttl_minutes', 30));

        // Token auf allen Einträgen dieser E-Mail setzen
        static::where('team_id', $this->team_id)
            ->where('email', $this->email)
            ->update([
                'token' => $token,
                'token_expires_at' => $expiresAt,
            ]);

        $this->token = $token;
        $this->token_expires_at = $expiresAt;

        return $token;
    }

    public function isTokenValid(string $token): bool
    {
        return $this->token === $token
            && $this->token_expires_at
            && $this->token_expires_at->isFuture();
    }

    /**
     * Clear token on all rows for this email+team.
     */
    public function clearToken(): void
    {
        static::where('team_id', $this->team_id)
            ->where('email', $this->email)
            ->update([
                'token' => null,
                'token_expires_at' => null,
            ]);

        $this->token = null;
        $this->token_expires_at = null;
    }

    /**
     * Get all entities this owner (email) has access to.
     */
    public static function entitiesForOwner(int $teamId, string $email): \Illuminate\Database\Eloquent\Collection
    {
        $entityIds = static::where('team_id', $teamId)
            ->where('email', $email)
            ->approved()
            ->pluck('entity_id');

        return SjEntity::whereIn('id', $entityIds)->get();
    }
}
