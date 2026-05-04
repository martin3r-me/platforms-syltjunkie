<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;

class SjRelationType extends Model
{
    use SoftDeletes;

    protected $table = 'sj_relation_types';

    protected $fillable = [
        'team_id',
        'code',
        'name',
        'inverse_name',
        'description',
        'icon',
        'is_directional',
        'is_hierarchical',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_directional' => 'boolean',
        'is_hierarchical' => 'boolean',
        'sort_order' => 'integer',
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

    public function relationships(): HasMany
    {
        return $this->hasMany(SjEntityRelationship::class, 'relation_type_id');
    }
}
