<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SjKeywordRanking extends Model
{
    protected $table = 'sj_keyword_rankings';

    protected $fillable = [
        'keyword_id',
        'entity_url_id',
        'position',
        'previous_position',
        'ranked_url',
        'captured_at',
        'search_engine',
        'device',
        'serp_features',
    ];

    protected $casts = [
        'captured_at' => 'date',
        'serp_features' => 'array',
    ];

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(SjKeyword::class, 'keyword_id');
    }

    public function entityUrl(): BelongsTo
    {
        return $this->belongsTo(SjEntityUrl::class, 'entity_url_id');
    }

    /**
     * Position-Delta (positiv = verbessert, negativ = verschlechtert).
     */
    public function getPositionDeltaAttribute(): ?int
    {
        if ($this->previous_position === null) {
            return null;
        }
        return $this->previous_position - $this->position;
    }
}
