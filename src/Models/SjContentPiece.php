<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

class SjContentPiece extends Model
{
    use SoftDeletes;

    protected $table = 'sj_content_pieces';

    protected $fillable = [
        'team_id',
        'title',
        'slug',
        'content_type',
        'status',
        'brief_notes',
        'body_markdown',
        'excerpt',
        'cover_image_id',
        'seo_title',
        'seo_description',
        'published_url',
        'published_at',
        'target_traffic_estimate',
        'target_value_cents',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidV7::generate();
            }
        });
    }

    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(SjKeyword::class, 'sj_content_keywords', 'content_piece_id', 'keyword_id')
            ->withPivot(['is_primary', 'current_position'])
            ->withTimestamps();
    }

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(SjEntity::class, 'sj_content_entities', 'content_piece_id', 'entity_id')
            ->withPivot(['display_order', 'is_primary', 'cta_type', 'cta_override_url'])
            ->withTimestamps();
    }

    public function performance(): HasMany
    {
        return $this->hasMany(SjContentPerformance::class, 'content_piece_id');
    }

    public function ctaEvents(): HasMany
    {
        return $this->hasMany(SjCtaEvent::class, 'content_piece_id');
    }

    public function primaryKeyword()
    {
        return $this->keywords()->wherePivot('is_primary', true);
    }

    public function channelPosts(): HasMany
    {
        return $this->hasMany(SjChannelPost::class, 'content_piece_id');
    }

    public function coverImage(): BelongsTo
    {
        return $this->belongsTo(SjImage::class, 'cover_image_id');
    }

    public function images(): BelongsToMany
    {
        return $this->belongsToMany(SjImage::class, 'sj_content_images', 'content_piece_id', 'image_id')
            ->withPivot(['sort_order', 'role'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function getTargetValueEuroAttribute(): ?float
    {
        return $this->target_value_cents !== null ? $this->target_value_cents / 100 : null;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'brief' => 'gray',
            'draft' => 'yellow',
            'review' => 'orange',
            'published' => 'green',
            'archived' => 'slate',
            default => 'gray',
        };
    }

    public function contentBlocks(): MorphMany
    {
        return $this->morphMany(SjContentBlock::class, 'blockable')
            ->where('is_active', true)
            ->orderBy('order');
    }
}
