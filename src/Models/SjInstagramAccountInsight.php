<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Integrations\Models\IntegrationsInstagramAccount;

class SjInstagramAccountInsight extends Model
{
    protected $table = 'sj_instagram_account_insights';

    protected $fillable = [
        'instagram_account_id',
        'insight_date',
        'current_name',
        'current_username',
        'current_biography',
        'current_profile_picture_url',
        'current_website',
        'current_followers',
        'current_follows',
        'follower_count',
        'impressions',
        'reach',
        'accounts_engaged',
        'total_interactions',
        'likes',
        'comments',
        'shares',
        'saves',
        'replies',
        'profile_views',
        'website_clicks',
        'email_contacts',
        'phone_call_clicks',
        'get_directions_clicks',
    ];

    protected $casts = [
        'insight_date' => 'date',
        'current_followers' => 'integer',
        'current_follows' => 'integer',
        'follower_count' => 'integer',
        'impressions' => 'integer',
        'reach' => 'integer',
        'accounts_engaged' => 'integer',
        'total_interactions' => 'integer',
        'likes' => 'integer',
        'comments' => 'integer',
        'shares' => 'integer',
        'saves' => 'integer',
        'replies' => 'integer',
        'profile_views' => 'integer',
        'website_clicks' => 'integer',
        'email_contacts' => 'integer',
        'phone_call_clicks' => 'integer',
        'get_directions_clicks' => 'integer',
    ];

    public function instagramAccount(): BelongsTo
    {
        return $this->belongsTo(IntegrationsInstagramAccount::class, 'instagram_account_id');
    }
}
