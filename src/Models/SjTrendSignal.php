<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class SjTrendSignal extends Model
{
    protected $table = 'sj_trend_signals';

    protected $fillable = [
        'team_id',
        'entity_id',
        'keyword_id',
        'entity_url_id',
        'signal_type',
        'severity',
        'title',
        'description',
        'metric_before',
        'metric_after',
        'metric_delta',
        'detected_at',
        'status',
        'context',
    ];

    protected $casts = [
        'detected_at' => 'date',
        'context' => 'array',
        'metric_before' => 'decimal:4',
        'metric_after' => 'decimal:4',
        'metric_delta' => 'decimal:4',
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

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(SjKeyword::class, 'keyword_id');
    }

    public function entityUrl(): BelongsTo
    {
        return $this->belongsTo(SjEntityUrl::class, 'entity_url_id');
    }
}
