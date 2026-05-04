<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class SjPageSnapshot extends Model
{
    protected $table = 'sj_page_snapshots';

    protected $fillable = [
        'team_id',
        'entity_url_id',
        'captured_at',
        'status_code',
        'title',
        'meta_description',
        'headings',
        'word_count',
        'content_length',
        'internal_links_count',
        'external_links_count',
        'image_count',
        'load_time',
        'onpage_score',
        'content_hash',
        'raw_response',
    ];

    protected $casts = [
        'captured_at' => 'date',
        'headings' => 'array',
        'raw_response' => 'array',
        'load_time' => 'decimal:2',
        'onpage_score' => 'decimal:2',
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
