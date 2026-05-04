<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Symfony\Component\Uid\UuidV7;

class SjEntity extends Model
{
    use SoftDeletes;

    protected $table = 'sj_entities';

    protected $fillable = [
        'team_id',
        'entity_type_id',
        'name',
        'slug',
        'description',
        'latitude',
        'longitude',
        'ort',
        'season',
        'status',
        'source',
        'extra_fields',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'extra_fields' => 'array',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
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

            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    public function entityType(): BelongsTo
    {
        return $this->belongsTo(SjEntityType::class, 'entity_type_id');
    }

    public function outgoingRelationships(): HasMany
    {
        return $this->hasMany(SjEntityRelationship::class, 'source_entity_id');
    }

    public function incomingRelationships(): HasMany
    {
        return $this->hasMany(SjEntityRelationship::class, 'target_entity_id');
    }

    public function entityUrls(): HasMany
    {
        return $this->hasMany(SjEntityUrl::class, 'entity_id');
    }
}
