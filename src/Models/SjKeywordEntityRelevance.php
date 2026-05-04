<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SjKeywordEntityRelevance extends Model
{
    protected $table = 'sj_keyword_entity_relevance';

    protected $fillable = [
        'keyword_id',
        'entity_id',
        'attribution_type',
        'confidence',
        'source',
    ];

    protected $casts = [
        'confidence' => 'decimal:2',
    ];

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(SjKeyword::class, 'keyword_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(SjEntity::class, 'entity_id');
    }
}
