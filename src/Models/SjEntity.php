<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
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
        'season',
        'status',
        'source',
        'extra_fields',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'extra_fields' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
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

    public function setGeometry(?array $geoJson): void
    {
        if ($geoJson) {
            DB::statement(
                'UPDATE sj_entities SET geometry = ST_GeomFromGeoJSON(CAST(? AS JSON), 1, 4326) WHERE id = ?',
                [json_encode($geoJson), $this->id]
            );
        } else {
            DB::statement('UPDATE sj_entities SET geometry = NULL WHERE id = ?', [$this->id]);
        }
    }

    public function getGeometryGeoJson(): ?array
    {
        try {
            $row = DB::selectOne(
                'SELECT ST_AsGeoJSON(geometry) as geo FROM sj_entities WHERE id = ? AND geometry IS NOT NULL',
                [$this->id]
            );

            return $row?->geo ? json_decode($row->geo, true) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function hasGeometry(): bool
    {
        try {
            return DB::selectOne(
                'SELECT COUNT(*) as cnt FROM sj_entities WHERE id = ? AND ST_IsValid(geometry)',
                [$this->id]
            )?->cnt > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function ortEntity(): ?self
    {
        return $this->outgoingRelationships()
            ->where('relation_type_id', 1) // lokalisiert_in
            ->where('is_active', true)
            ->first()?->targetEntity;
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

    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(SjKeyword::class, 'sj_keyword_entity_relevance', 'entity_id', 'keyword_id')
            ->withPivot(['attribution_type', 'confidence', 'source'])
            ->withTimestamps();
    }

    public function keywordGaps(): HasMany
    {
        return $this->hasMany(SjKeywordGap::class, 'entity_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(SjEntityScore::class, 'entity_id');
    }

    public function latestScore(): HasOne
    {
        return $this->hasOne(SjEntityScore::class, 'entity_id')->latestOfMany('captured_at');
    }

    public function ctaConfigs(): HasMany
    {
        return $this->hasMany(SjEntityCtaConfig::class, 'entity_id');
    }

    public function ctaEvents(): HasMany
    {
        return $this->hasMany(SjCtaEvent::class, 'entity_id');
    }

    public function trendSignals(): HasMany
    {
        return $this->hasMany(SjTrendSignal::class, 'entity_id');
    }

    public function contentPieces(): BelongsToMany
    {
        return $this->belongsToMany(SjContentPiece::class, 'sj_content_entities', 'entity_id', 'content_piece_id')
            ->withPivot(['display_order', 'is_primary', 'cta_type', 'cta_override_url'])
            ->withTimestamps();
    }

    public function images(): BelongsToMany
    {
        return $this->belongsToMany(SjImage::class, 'sj_image_entity', 'entity_id', 'sj_image_id')
            ->withPivot(['sort_order', 'is_primary', 'source', 'distance_m'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function primaryImage(): BelongsToMany
    {
        return $this->images()->wherePivot('is_primary', true);
    }

    public function channelPosts(): HasMany
    {
        return $this->hasMany(SjChannelPost::class, 'entity_id');
    }

    public function weather(): HasMany
    {
        return $this->hasMany(SjWeather::class, 'entity_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SjEntityEvent::class, 'entity_id');
    }

    public function upcomingEvents(): HasMany
    {
        return $this->events()->where('starts_at', '>=', now())->orderBy('starts_at');
    }
}
