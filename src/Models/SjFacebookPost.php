<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Models\ContextFile;
use Platform\Core\Services\ContextFileService;
use Platform\Integrations\Models\IntegrationsFacebookPage;
use Symfony\Component\Uid\UuidV7;

class SjFacebookPost extends Model
{
    protected $table = 'sj_facebook_posts';

    protected $fillable = [
        'team_id',
        'facebook_page_id',
        'external_id',
        'message',
        'media_url',
        'permalink_url',
        'published_at',
        'like_count',
        'comment_count',
        'share_count',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'like_count' => 'integer',
        'comment_count' => 'integer',
        'share_count' => 'integer',
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
                    \Log::error('Error deleting ContextFile for SjFacebookPost', [
                        'context_file_id' => $contextFile->id,
                        'post_id' => $model->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    public function facebookPage(): BelongsTo
    {
        return $this->belongsTo(IntegrationsFacebookPage::class, 'facebook_page_id');
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

        return $this->media_url;
    }
}
