<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class SjPageChange extends Model
{
    protected $table = 'sj_page_changes';

    protected $fillable = [
        'team_id',
        'entity_url_id',
        'detected_at',
        'change_type',
        'severity',
        'old_value',
        'new_value',
        'delta',
        'context',
    ];

    protected $casts = [
        'detected_at' => 'date',
        'context' => 'array',
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
