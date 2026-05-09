<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'geometry',
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
        'geometry' => 'array',
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
            ->withPivot(['sort_order', 'is_primary'])
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
}
