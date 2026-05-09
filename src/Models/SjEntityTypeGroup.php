<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;

class SjEntityTypeGroup extends Model
{
    use SoftDeletes;

    protected $table = 'sj_entity_type_groups';

    protected $fillable = [
        'team_id',
        'code',
        'prefix',
        'name',
        'nav_label',
        'singular',
        'description',
        'icon',
        'color',
        'template',
        'show_on_map',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_on_map' => 'boolean',
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

    public function entityTypes(): HasMany
    {
        return $this->hasMany(SjEntityType::class, 'group_id');
    }
}
