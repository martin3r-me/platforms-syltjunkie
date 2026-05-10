<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;

class SjPipelineSlot extends Model
{
    protected $table = 'sj_pipeline_slots';

    protected $fillable = [
        'team_id',
        'name',
        'color',
        'order',
    ];

    protected $casts = [
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
                $maxOrder = self::where('team_id', $model->team_id)->max('order') ?? 0;
                $model->order = $maxOrder + 1;
            }
        });
    }

    public function cards(): HasMany
    {
        return $this->hasMany(SjPipelineCard::class, 'slot_id')->orderBy('order');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }
}
