<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;

class SjKeyword extends Model
{
    protected $table = 'sj_keywords';

    protected $fillable = [
        'team_id',
        'keyword',
        'search_volume',
        'cpc_cents',
        'competition',
        'keyword_difficulty',
        'search_intent',
        'topic',
        'monthly_volumes',
        'peak_month',
        'seasonality_index',
        'last_fetched_at',
    ];

    protected $casts = [
        'monthly_volumes' => 'array',
        'competition' => 'decimal:3',
        'seasonality_index' => 'decimal:2',
        'last_fetched_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidV7::generate();
            }
        });
    }

    public function rankings(): HasMany
    {
        return $this->hasMany(SjKeywordRanking::class, 'keyword_id');
    }

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(SjEntity::class, 'sj_keyword_entity_relevance', 'keyword_id', 'entity_id')
            ->withPivot(['attribution_type', 'confidence', 'source'])
            ->withTimestamps();
    }

    public function gaps(): HasMany
    {
        return $this->hasMany(SjKeywordGap::class, 'keyword_id');
    }

    public function contentPieces(): BelongsToMany
    {
        return $this->belongsToMany(SjContentPiece::class, 'sj_content_keywords', 'keyword_id', 'content_piece_id')
            ->withPivot(['is_primary', 'current_position'])
            ->withTimestamps();
    }

    /**
     * CPC als Euro (convenience accessor).
     */
    public function getCpcEuroAttribute(): ?float
    {
        return $this->cpc_cents !== null ? $this->cpc_cents / 100 : null;
    }

    /**
     * Median des monatlichen Suchvolumens.
     */
    public function getMedianVolumeAttribute(): ?int
    {
        $mv = $this->monthly_volumes;
        if (!is_array($mv) || count($mv) < 2) {
            return $this->search_volume;
        }

        $values = array_values($mv);
        sort($values);
        $count = count($values);
        $mid = intdiv($count, 2);

        return $count % 2 === 0
            ? (int) round(($values[$mid - 1] + $values[$mid]) / 2)
            : $values[$mid];
    }

    /**
     * Niedrigstes monatliches Suchvolumen.
     */
    public function getMinVolumeAttribute(): ?int
    {
        $mv = $this->monthly_volumes;
        if (!is_array($mv) || empty($mv)) {
            return null;
        }
        return (int) min($mv);
    }

    /**
     * Höchstes monatliches Suchvolumen.
     */
    public function getMaxVolumeAttribute(): ?int
    {
        $mv = $this->monthly_volumes;
        if (!is_array($mv) || empty($mv)) {
            return null;
        }
        return (int) max($mv);
    }
}
