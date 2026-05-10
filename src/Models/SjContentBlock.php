<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Symfony\Component\Uid\UuidV7;

class SjContentBlock extends Model
{
    protected $table = 'sj_content_blocks';

    protected $fillable = [
        'team_id',
        'blockable_type',
        'blockable_id',
        'block_type',
        'content',
        'order',
        'is_active',
    ];

    protected $casts = [
        'content' => 'array',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidV7::generate();
            }
        });
    }

    public function blockable(): MorphTo
    {
        return $this->morphTo();
    }
}
