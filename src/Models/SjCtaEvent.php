<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SjCtaEvent extends Model
{
    protected $table = 'sj_cta_events';

    protected $fillable = [
        'entity_id',
        'content_piece_id',
        'cta_type',
        'event_date',
        'clicks',
    ];

    protected $casts = [
        'event_date' => 'date',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(SjEntity::class, 'entity_id');
    }

    public function contentPiece(): BelongsTo
    {
        return $this->belongsTo(SjContentPiece::class, 'content_piece_id');
    }
}
