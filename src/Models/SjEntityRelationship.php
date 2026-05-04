<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class SjEntityRelationship extends Model
{
    use SoftDeletes;

    protected $table = 'sj_entity_relationships';

    protected $fillable = [
        'team_id',
        'source_entity_id',
        'target_entity_id',
        'relation_type_id',
        'description',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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

    public function sourceEntity(): BelongsTo
    {
        return $this->belongsTo(SjEntity::class, 'source_entity_id');
    }

    public function targetEntity(): BelongsTo
    {
        return $this->belongsTo(SjEntity::class, 'target_entity_id');
    }

    public function relationType(): BelongsTo
    {
        return $this->belongsTo(SjRelationType::class, 'relation_type_id');
    }
}
