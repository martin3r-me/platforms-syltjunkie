<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SjKeywordGap extends Model
{
    protected $table = 'sj_keyword_gaps';

    protected $fillable = [
        'entity_id',
        'keyword_id',
        'competitor_entity_id',
        'competitor_position',
        'opportunity_value_cents',
        'captured_at',
    ];

    protected $casts = [
        'captured_at' => 'date',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(SjEntity::class, 'entity_id');
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(SjKeyword::class, 'keyword_id');
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(SjEntity::class, 'competitor_entity_id');
    }

    public function getOpportunityEuroAttribute(): float
    {
        return $this->opportunity_value_cents / 100;
    }
}
