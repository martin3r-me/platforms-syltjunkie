<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SjInstagramMediaInsight extends Model
{
    protected $table = 'sj_instagram_media_insights';

    protected $fillable = [
        'instagram_media_id',
        'insight_date',
        'impressions',
        'reach',
        'saved',
        'comments',
        'likes',
        'shares',
        'total_interactions',
        'replies',
        'navigation',
        'plays',
        'clips_replays_count',
        'ig_reels_aggregated_all_plays_count',
        'ig_reels_avg_watch_time',
        'ig_reels_video_view_total_time',
    ];

    protected $casts = [
        'insight_date' => 'date',
        'impressions' => 'integer',
        'reach' => 'integer',
        'saved' => 'integer',
        'comments' => 'integer',
        'likes' => 'integer',
        'shares' => 'integer',
        'total_interactions' => 'integer',
        'replies' => 'integer',
        'navigation' => 'array',
        'plays' => 'integer',
        'clips_replays_count' => 'integer',
        'ig_reels_aggregated_all_plays_count' => 'integer',
        'ig_reels_avg_watch_time' => 'integer',
        'ig_reels_video_view_total_time' => 'integer',
    ];

    public function instagramMedia(): BelongsTo
    {
        return $this->belongsTo(SjInstagramMedia::class, 'instagram_media_id');
    }
}
