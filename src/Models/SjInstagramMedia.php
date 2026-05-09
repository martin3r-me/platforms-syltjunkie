<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Platform\Core\Models\ContextFile;
use Platform\Core\Services\ContextFileService;
use Platform\Integrations\Models\IntegrationsInstagramAccount;
use Symfony\Component\Uid\UuidV7;

class SjInstagramMedia extends Model
{
    protected $table = 'sj_instagram_media';

    protected $fillable = [
        'team_id',
        'instagram_account_id',
        'external_id',
        'caption',
        'media_type',
        'media_url',
        'permalink',
        'thumbnail_url',
        'timestamp',
        'like_count',
        'comments_count',
        'is_story',
        'insights_available',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'like_count' => 'integer',
        'comments_count' => 'integer',
        'is_story' => 'boolean',
        'insights_available' => 'boolean',
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

        static::deleting(function ($model) {
            $contextFileService = app(ContextFileService::class);
            foreach ($model->contextFiles()->get() as $contextFile) {
                try {
                    $contextFileService->delete($contextFile->id);
                } catch (\Exception $e) {
                    \Log::error('Error deleting ContextFile for SjInstagramMedia', [
                        'context_file_id' => $contextFile->id,
                        'media_id' => $model->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    public function instagramAccount(): BelongsTo
    {
        return $this->belongsTo(IntegrationsInstagramAccount::class, 'instagram_account_id');
    }

    public function insights(): HasMany
    {
        return $this->hasMany(SjInstagramMediaInsight::class, 'instagram_media_id');
    }

    public function latestInsight(): HasOne
    {
        return $this->hasOne(SjInstagramMediaInsight::class, 'instagram_media_id')->latestOfMany('insight_date');
    }

    public function contextFiles()
    {
        return $this->hasMany(ContextFile::class, 'context_id', 'id')
            ->where('context_type', static::class);
    }

    public function getThumbnailAttribute(): ?string
    {
        $cf = $this->contextFiles()
            ->whereJsonContains('meta->role', 'primary')
            ->first();

        if ($cf) {
            $thumb = $cf->thumbnail;
            if ($thumb) {
                return ContextFileService::generateUrl($thumb->disk, $thumb->path, $thumb->token, 'core.context-files.variant');
            }
            return ContextFileService::generateUrl($cf->disk, $cf->path, $cf->token, 'core.context-files.serve');
        }

        return $this->thumbnail_url;
    }
}
