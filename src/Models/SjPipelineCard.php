<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class SjPipelineCard extends Model
{
    use SoftDeletes;

    protected $table = 'sj_pipeline_cards';

    protected $fillable = [
        'team_id',
        'slot_id',
        'name',
        'url',
        'entity_type_id',
        'latitude',
        'longitude',
        'notes',
        'order',
        'converted_at',
        'converted_entity_id',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'converted_at' => 'datetime',
        'order' => 'integer',
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

            if (!$model->order) {
                $maxOrder = self::where('slot_id', $model->slot_id)->max('order') ?? 0;
                $model->order = $maxOrder + 1;
            }
        });
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(SjPipelineSlot::class, 'slot_id');
    }

    public function entityType(): BelongsTo
    {
        return $this->belongsTo(SjEntityType::class, 'entity_type_id');
    }

    public function convertedEntity(): BelongsTo
    {
        return $this->belongsTo(SjEntity::class, 'converted_entity_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('converted_at');
    }

    public function scopeConverted($query)
    {
        return $query->whereNotNull('converted_at');
    }
}
