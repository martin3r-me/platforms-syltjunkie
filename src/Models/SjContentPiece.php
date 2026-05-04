<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function getTargetValueEuroAttribute(): ?float
    {
        return $this->target_value_cents !== null ? $this->target_value_cents / 100 : null;
    }
}
