<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Platform\Core\Models\ContextFile;
use Platform\Core\Services\ContextFileService;
use Symfony\Component\Uid\UuidV7;

class SjImage extends Model
{
    use SoftDeletes;

    protected $table = 'sj_images';

    protected $fillable = [
        'team_id',
        'context_file_id',
        'latitude',
        'longitude',
        'title',
        'description',
        'photographer',
        'taken_at',
        'tags',
    ];

    protected $casts = [
        'tags' => 'array',
        'taken_at' => 'date',
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
        });

        static::saving(function ($model) {
            if ($model->isDirty(['latitude', 'longitude']) && $model->latitude && $model->longitude) {
                $model->_syncLocation = true;
            }
        });

        static::saved(function ($model) {
            if ($model->_syncLocation ?? false) {
                DB::statement(
                    'UPDATE sj_images SET location = ST_SRID(POINT(?, ?), 4326) WHERE id = ?',
                    [$model->longitude, $model->latitude, $model->id]
                );
                $model->_syncLocation = false;
            }
        });
    }

    public function contextFile(): BelongsTo
    {
        return $this->belongsTo(ContextFile::class);
    }

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(SjEntity::class, 'sj_image_entity', 'sj_image_id', 'entity_id')
            ->withPivot(['sort_order', 'is_primary', 'source', 'distance_m'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * Haversine-basierte Nearby-Suche.
     */
    public function scopeNearby($query, float $lat, float $lng, float $radiusKm = 5)
    {
        $haversine = "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))";

        return $query->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereRaw("{$haversine} < ?", [$lat, $lng, $lat, $radiusKm])
            ->orderByRaw("{$haversine}", [$lat, $lng, $lat]);
    }

    public function scopeWithinGeoJson($query, array $geoJson)
    {
        return $query->whereNotNull('location')
            ->whereRaw('ST_Contains(ST_GeomFromGeoJSON(?, 1, 4326), location)', [json_encode($geoJson)]);
    }

    public function scopeAlongRoute($query, array $lineStringGeoJson, float $bufferMeters = 50)
    {
        $bufferDeg = $bufferMeters / 111320;

        return $query->whereNotNull('location')
            ->whereRaw('ST_Contains(ST_Buffer(ST_GeomFromGeoJSON(?, 1, 4326), ?), location)', [
                json_encode($lineStringGeoJson), $bufferDeg,
            ]);
    }

    public function scopeWithTag($query, string $tag)
    {
        return $query->whereJsonContains('tags', $tag);
    }

    public function getUrlAttribute(): string
    {
        return $this->contextFile?->url ?? '';
    }

    public function channelPosts(): BelongsToMany
    {
        return $this->belongsToMany(SjChannelPost::class, 'sj_channel_post_images', 'sj_image_id', 'channel_post_id')
            ->withPivot(['sort_order', 'role'])
            ->withTimestamps();
    }

    public function contentPieces(): BelongsToMany
    {
        return $this->belongsToMany(SjContentPiece::class, 'sj_content_images', 'image_id', 'content_piece_id')
            ->withPivot(['sort_order', 'role'])
            ->withTimestamps();
    }

    public function getThumbnailUrlAttribute(): string
    {
        $thumbnail = $this->contextFile?->thumbnail;
        if ($thumbnail) {
            return ContextFileService::generateUrl($thumbnail->disk, $thumbnail->path, $thumbnail->token, 'core.context-files.variant');
        }

        return $this->url;
    }
}
