<?php

namespace Platform\Syltjunkie\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationsInstagramAccount;
use Platform\Integrations\Services\MetaIntegrationService;
use Platform\Syltjunkie\Models\SjInstagramAccountInsight;
use Platform\Syltjunkie\Models\SjInstagramMedia;
use Platform\Syltjunkie\Models\SjInstagramMediaInsight;

class SjInstagramInsightsService
{
    protected MetaIntegrationService $metaService;

    public function __construct(MetaIntegrationService $metaService)
    {
        $this->metaService = $metaService;
    }

    public function syncAccountInsights(IntegrationsInstagramAccount $account, IntegrationConnection $connection): array
    {
        $insights = [];

        $accountDetails = $this->fetchAccountDetails($account, $connection);
        if (!empty($accountDetails)) {
            $insights['account_details'] = $accountDetails;
        }

        $dailyMetrics = $this->fetchDailyMetrics($account, $connection);
        if (!empty($dailyMetrics)) {
            $this->saveDailyMetrics($account, $dailyMetrics);
            $insights['daily_metrics'] = $dailyMetrics;
        }

        $totalValueMetrics = $this->fetchTotalValueMetrics($account, $connection);
        if (!empty($totalValueMetrics)) {
            $this->saveTotalValueMetrics($account, $totalValueMetrics);
            $insights['total_value_metrics'] = $totalValueMetrics;
        }

        return $insights;
    }

    public function syncMediaInsights(IntegrationsInstagramAccount $account, IntegrationConnection $connection): array
    {
        $accessToken = $this->metaService->getValidAccessToken($connection);
        if (!$accessToken) {
            return ['synced' => 0, 'skipped' => 0];
        }

        $mediaItems = SjInstagramMedia::where('instagram_account_id', $account->id)
            ->where('insights_available', true)
            ->get();

        $syncedCount = 0;
        $skippedCount = 0;

        foreach ($mediaItems as $media) {
            try {
                $insights = $this->fetchMediaInsights(
                    $media->external_id,
                    strtolower($media->media_type),
                    $accessToken
                );

                if (isset($insights['insights_available']) && !$insights['insights_available']) {
                    $media->update(['insights_available' => false]);
                    $skippedCount++;
                    continue;
                }

                if (!empty($insights)) {
                    SjInstagramMediaInsight::updateOrCreate(
                        [
                            'instagram_media_id' => $media->id,
                            'insight_date' => Carbon::now()->format('Y-m-d'),
                        ],
                        $insights
                    );
                    $syncedCount++;
                }
            } catch (\Exception $e) {
                Log::error('SjInstagramInsightsService: Error syncing media insights', [
                    'media_id' => $media->id,
                    'error' => $e->getMessage(),
                ]);
                $skippedCount++;
            }
        }

        return ['synced' => $syncedCount, 'skipped' => $skippedCount];
    }

    public function fetchAccountDetails(IntegrationsInstagramAccount $account, IntegrationConnection $connection): array
    {
        $accessToken = $this->metaService->getValidAccessToken($connection);
        if (!$accessToken) {
            return [];
        }

        $apiVersion = config('integrations.oauth2.providers.meta.api_version', '21.0');

        $response = Http::get("https://graph.facebook.com/{$apiVersion}/{$account->external_id}", [
            'fields' => 'name,username,biography,profile_picture_url,website,followers_count,follows_count',
            'access_token' => $accessToken,
        ]);

        if ($response->failed()) {
            Log::error('SjInstagramInsightsService: Failed to fetch account details', [
                'account_id' => $account->id,
                'error' => $response->json()['error'] ?? [],
            ]);
            return [];
        }

        $data = $response->json();

        SjInstagramAccountInsight::updateOrCreate(
            [
                'instagram_account_id' => $account->id,
                'insight_date' => Carbon::now()->format('Y-m-d'),
            ],
            [
                'current_name' => $data['name'] ?? null,
                'current_username' => $data['username'] ?? null,
                'current_biography' => $data['biography'] ?? null,
                'current_profile_picture_url' => $data['profile_picture_url'] ?? null,
                'current_website' => $data['website'] ?? null,
                'current_followers' => $data['followers_count'] ?? null,
                'current_follows' => $data['follows_count'] ?? null,
            ]
        );

        return $data;
    }

    public function fetchDailyMetrics(IntegrationsInstagramAccount $account, IntegrationConnection $connection): array
    {
        return $this->fetchAccountInsights($account, $connection, ['follower_count', 'impressions', 'reach'], 'day');
    }

    public function fetchTotalValueMetrics(IntegrationsInstagramAccount $account, IntegrationConnection $connection): array
    {
        return $this->fetchAccountInsights(
            $account,
            $connection,
            ['accounts_engaged', 'total_interactions', 'likes', 'comments', 'shares', 'saves', 'replies'],
            'day',
            'total_value'
        );
    }

    protected function fetchAccountInsights(IntegrationsInstagramAccount $account, IntegrationConnection $connection, array $metrics, string $period = 'day', ?string $metricType = null): array
    {
        $accessToken = $this->metaService->getValidAccessToken($connection);
        if (!$accessToken) {
            return [];
        }

        $apiVersion = config('integrations.oauth2.providers.meta.api_version', '21.0');

        $params = [
            'metric' => implode(',', $metrics),
            'period' => $period,
            'access_token' => $accessToken,
        ];

        if ($metricType) {
            $params['metric_type'] = $metricType;
        }

        $response = Http::get("https://graph.facebook.com/{$apiVersion}/{$account->external_id}/insights", $params);

        if ($response->failed()) {
            Log::error('SjInstagramInsightsService: Failed to fetch account insights', [
                'account_id' => $account->id,
                'error' => $response->json()['error'] ?? [],
            ]);
            return [];
        }

        $data = $response->json();
        $insights = [];

        if (isset($data['data'])) {
            foreach ($data['data'] as $insight) {
                $values = [];
                foreach ($insight['values'] ?? [] as $value) {
                    if ($metricType === 'total_value') {
                        $values[] = $value['total_value']['value'] ?? 0;
                    } else {
                        $values[] = [
                            'value' => $value['value'] ?? 0,
                            'end_time' => $value['end_time'] ?? null,
                        ];
                    }
                }
                $insights[$insight['name']] = $values;
            }
        }

        return $insights;
    }

    protected function fetchMediaInsights(string $mediaId, string $mediaType, string $accessToken): array
    {
        $apiVersion = config('integrations.oauth2.providers.meta.api_version', '21.0');

        $metrics = ['impressions', 'reach', 'saved', 'comments', 'likes', 'shares', 'total_interactions'];

        if ($mediaType === 'story') {
            $metrics = array_merge($metrics, ['replies', 'navigation']);
        }

        if ($mediaType === 'reel') {
            $metrics = array_merge($metrics, [
                'plays',
                'clips_replays_count',
                'ig_reels_aggregated_all_plays_count',
                'ig_reels_avg_watch_time',
                'ig_reels_video_view_total_time',
            ]);
        }

        $params = [
            'metric' => implode(',', $metrics),
            'access_token' => $accessToken,
        ];

        if ($mediaType === 'story') {
            $params['breakdown'] = 'story_navigation_action_type';
        }

        $response = Http::get("https://graph.facebook.com/{$apiVersion}/{$mediaId}/insights", $params);

        if ($response->failed()) {
            $error = $response->json()['error'] ?? [];

            if (isset($error['error_subcode']) && $error['error_subcode'] === 2108006) {
                return ['insights_available' => false];
            }

            Log::error('SjInstagramInsightsService: Failed to fetch media insights', [
                'media_id' => $mediaId,
                'error' => $error,
            ]);
            return [];
        }

        $data = $response->json();
        $insights = [];

        if (isset($data['data'])) {
            foreach ($data['data'] as $insight) {
                foreach ($insight['values'] ?? [] as $value) {
                    $insights[$insight['name']] = $value['value'] ?? 0;
                }
            }
        }

        return $insights;
    }

    protected function saveDailyMetrics(IntegrationsInstagramAccount $account, array $metrics): void
    {
        foreach ($metrics as $metricName => $values) {
            if (empty($values) || !is_array($values)) {
                continue;
            }

            foreach ($values as $valueData) {
                $value = is_array($valueData) ? ($valueData['value'] ?? 0) : $valueData;
                $endTime = is_array($valueData) && isset($valueData['end_time'])
                    ? Carbon::parse($valueData['end_time'])->format('Y-m-d')
                    : Carbon::now()->format('Y-m-d');

                SjInstagramAccountInsight::updateOrCreate(
                    [
                        'instagram_account_id' => $account->id,
                        'insight_date' => $endTime,
                    ],
                    [
                        $metricName => $value,
                    ]
                );
            }
        }
    }

    protected function saveTotalValueMetrics(IntegrationsInstagramAccount $account, array $metrics): void
    {
        $insightDate = Carbon::now()->format('Y-m-d');

        foreach ($metrics as $metricName => $values) {
            if (empty($values) || !is_array($values)) {
                continue;
            }

            $totalValue = is_array($values) && isset($values[0])
                ? (is_numeric($values[0]) ? (int)$values[0] : 0)
                : (is_numeric($values) ? (int)$values : 0);

            SjInstagramAccountInsight::updateOrCreate(
                [
                    'instagram_account_id' => $account->id,
                    'insight_date' => $insightDate,
                ],
                [
                    $metricName => $totalValue,
                ]
            );
        }
    }
}
