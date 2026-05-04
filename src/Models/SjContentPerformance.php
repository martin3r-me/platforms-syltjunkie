<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SjContentPerformance extends Model
{
    protected $table = 'sj_content_performance';

    protected $fillable = [
        'content_piece_id',
        'captured_at',
        'pageviews',
        'unique_visitors',
        'avg_time_seconds',
        'bounce_rate',
        'cta_clicks_total',
    ];

    protected $casts = [
        'captured_at' => 'date',
        'bounce_rate' => 'decimal:1',
    ];

    public function contentPiece(): BelongsTo
    {
        return $this->belongsTo(SjContentPiece::class, 'content_piece_id');
    }
}
