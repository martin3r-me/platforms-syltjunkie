<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class SjUrlSnapshot extends Model
{
    protected $table = 'sj_url_snapshots';

    protected $fillable = [
        'team_id',
        'entity_url_id',
        'captured_at',
        'keywords',
        'organic_traffic_estimate',
        'domain_authority',
        'backlinks_count',
        'raw_response',
    ];

    protected $casts = [
        'captured_at' => 'date',
        'keywords' => 'array',
        'raw_response' => 'array',
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

    public function entityUrl(): BelongsTo
    {
        return $this->belongsTo(SjEntityUrl::class, 'entity_url_id');
    }
}
