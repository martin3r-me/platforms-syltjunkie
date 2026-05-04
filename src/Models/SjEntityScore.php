<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class SjEntityScore extends Model
{
    protected $table = 'sj_entity_scores';

    protected $fillable = [
        'entity_id',
        'captured_at',
        'visibility_score',
        'visibility_trend',
        'organic_value_cents',
        'estimated_monthly_traffic',
        'direct_keywords_count',
        'brand_keywords_count',
        'top10_keywords_count',
        'total_keywords_count',
        'score_google_organic',
        'score_google_maps',
        'score_tripadvisor',
        'score_instagram',
        'platforms_active',
        'avg_review_rating',
        'total_review_count',
        'top_opportunity',
        'top_opportunity_value_cents',
    ];

    protected $casts = [
        'captured_at' => 'date',
        'visibility_trend' => 'decimal:2',
        'avg_review_rating' => 'decimal:1',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidV7::generate();
            }
        });
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(SjEntity::class, 'entity_id');
    }

    public function getOrganicValueEuroAttribute(): float
    {
        return $this->organic_value_cents / 100;
    }

    public function getTopOpportunityValueEuroAttribute(): ?float
    {
        return $this->top_opportunity_value_cents !== null
            ? $this->top_opportunity_value_cents / 100
            : null;
    }
}
