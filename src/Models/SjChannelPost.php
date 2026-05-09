<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Symfony\Component\Uid\UuidV7;

class SjChannelPost extends Model
{
    use SoftDeletes;

    protected $table = 'sj_channel_posts';

    protected $fillable = [
        'team_id',
        'channel_id',
        'entity_id',
        'content_piece_id',
        'post_type',
        'status',
        'caption',
        'hashtags',
        'meta_data',
        'scheduled_at',
        'published_at',
        'external_post_id',
        'error_message',
        'created_by',
    ];

    protected $casts = [
        'hashtags' => 'array',
        'meta_data' => 'array',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
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

    public function channel(): BelongsTo
    {
        return $this->belongsTo(SjChannel::class, 'channel_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(SjEntity::class, 'entity_id');
    }

    public function contentPiece(): BelongsTo
    {
        return $this->belongsTo(SjContentPiece::class, 'content_piece_id');
    }

    public function images(): BelongsToMany
    {
        return $this->belongsToMany(SjImage::class, 'sj_channel_post_images', 'channel_post_id', 'sj_image_id')
            ->withPivot(['sort_order', 'role'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }
}
