<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;

class SjChannel extends Model
{
    use SoftDeletes;

    protected $table = 'sj_channels';

    protected $fillable = [
        'team_id',
        'type',
        'name',
        'status',
        'config',
        'sync_status',
        'sync_error',
        'last_synced_at',
    ];

    protected $casts = [
        'config' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function isSyncing(): bool
    {
        return $this->sync_status === 'syncing';
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
        });
    }

    public function posts(): HasMany
    {
        return $this->hasMany(SjChannelPost::class, 'channel_id');
    }

    public function getIntegrationConnectionIdAttribute(): ?int
    {
        return $this->config['integration_connection_id'] ?? null;
    }

    public function getInstagramAccountIdAttribute(): ?int
    {
        return $this->config['instagram_account_id'] ?? null;
    }

    public function getFacebookPageIdAttribute(): ?int
    {
        return $this->config['facebook_page_id'] ?? null;
    }
}
