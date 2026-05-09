<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Symfony\Component\Uid\UuidV7;

class SjEntityUrl extends Model
{
    use SoftDeletes;

    protected $table = 'sj_entity_urls';

    protected $fillable = [
        'team_id',
        'entity_id',
        'url',
        'platform',
        'is_primary',
        'is_active',
        'google_place_id',
        'last_checked_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

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

    public function entity(): BelongsTo
    {
        return $this->belongsTo(SjEntity::class, 'entity_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(SjUrlSnapshot::class, 'entity_url_id');
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(SjUrlSnapshot::class, 'entity_url_id')->latestOfMany('captured_at');
    }

    public function keywordRankings(): HasMany
    {
        return $this->hasMany(SjKeywordRanking::class, 'entity_url_id');
    }

    public function pageSnapshots(): HasMany
    {
        return $this->hasMany(SjPageSnapshot::class, 'entity_url_id');
    }

    public function latestPageSnapshot(): HasOne
    {
        return $this->hasOne(SjPageSnapshot::class, 'entity_url_id')->latestOfMany('captured_at');
    }

    public function pageChanges(): HasMany
    {
        return $this->hasMany(SjPageChange::class, 'entity_url_id');
    }
}
