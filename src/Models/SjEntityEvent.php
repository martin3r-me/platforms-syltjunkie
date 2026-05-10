<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class SjEntityEvent extends Model
{
    use SoftDeletes;

    protected $table = 'sj_entity_events';

    protected $fillable = [
        'team_id',
        'entity_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'is_all_day',
        'location_detail',
        'status',
        'metadata',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_all_day' => 'boolean',
        'metadata' => 'array',
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
}
