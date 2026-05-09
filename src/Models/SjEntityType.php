<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;

class SjEntityType extends Model
{
    use SoftDeletes;

    protected $table = 'sj_entity_types';

    protected $fillable = [
        'team_id',
        'group_id',
        'code',
        'name',
        'description',
        'icon',
        'color',
        'extra_field_schema',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'extra_field_schema' => 'array',
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

    public function group(): BelongsTo
    {
        return $this->belongsTo(SjEntityTypeGroup::class, 'group_id');
    }

    public function entities(): HasMany
    {
        return $this->hasMany(SjEntity::class, 'entity_type_id');
    }
}
