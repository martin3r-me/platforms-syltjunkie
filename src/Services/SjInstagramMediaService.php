<?php

namespace Platform\Syltjunkie\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\ContextFile;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationsInstagramAccount;
use Platform\Integrations\Services\MetaIntegrationService;
use Platform\Syltjunkie\Models\SjInstagramMedia;

class SjInstagramMediaService
{
    protected MetaIntegrationService $metaService;
    protected SjMediaDownloadService $mediaDownloadService;

    public function __construct(MetaIntegrationService $metaService, SjMediaDownloadService $mediaDownloadService)
    {
        $this->metaService = $metaService;
        $this->mediaDownloadService = $mediaDownloadService;
    }

    public function fetchMedia(IntegrationsInstagramAccount $account, IntegrationConnection $connection, int $limit = 1000): array
    {
        $accessToken = $this->metaService->getValidAccessToken($connection);

        if (!$accessToken) {
            throw new \Exception('Kein gültiger Access Token für diese Connection.');
        }

        $apiVersion = config('integrations.oauth2.providers.meta.api_version', '21.0');
        $allMedia = [];

        $params = [
            'fields' => 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp,like_count,comments_count,children{media_type,media_url}',
            'access_token' => $accessToken,
            'limit' => $limit,
        ];

        $url = "https://graph.facebook.com/{$apiVersion}/{$account->external_id}/media";

        do {
            $response = Http::get($url, $params);

            if ($response->failed()) {
                Log::error('SjInstagramMediaService: Failed to fetch media', [
                    'account_id' => $account->id,
                    'error' => $response->json()['error'] ?? [],
                ]);
                break;
            }

            $data = $response->json();

            if (isset($data['data'])) {
                foreach ($data['data'] as $mediaData) {
                    $allMedia[] = $this->normalizeMediaData($mediaData, false);
                }
            }

            $url = $data['paging']['next'] ?? null;
            $params = []; // next URL contains params
        } while ($url);

        // Stories
        $storiesResponse = Http::get(
            "https://graph.facebook.com/{$apiVersion}/{$account->external_id}/stories",
            [
                'fields' => 'id,media_type,media_url,permalink,timestamp',
                'access_token' => $accessToken,
            ]
        );

        if ($storiesResponse->successful() && isset($storiesResponse->json()['data'])) {
            foreach ($storiesResponse->json()['data'] as $storyData) {
                $allMedia[] = $this->normalizeMediaData($storyData, true);
            }
        }

        return $allMedia;
    }

    public function syncMedia(IntegrationsInstagramAccount $account, IntegrationConnection $connection, int $teamId, int $limit = 1000, ?\Illuminate\Console\Command $command = null): array
    {
        $mediaData = $this->fetchMedia($account, $connection, $limit);
        $syncedMedia = [];
        $retrievedIds = [];

        $totalCount = count($mediaData);
        if ($command) {
            $command->info("     {$totalCount} Media-Item(s) gefunden");
        }

        foreach ($mediaData as $index => $data) {
            $retrievedIds[] = $data['media_id'];

            $media = SjInstagramMedia::updateOrCreate(
                [
                    'external_id' => $data['media_id'],
                    'instagram_account_id' => $account->id,
                ],
                [
                    'team_id' => $teamId,
                    'caption' => $data['caption'],
                    'media_type' => $data['media_type'],
                    'media_url' => $data['media_url'],
                    'permalink' => $data['permalink'],
                    'thumbnail_url' => $data['thumbnail_url'],
                    'timestamp' => $data['timestamp'],
                    'like_count' => $data['like_count'],
                    'comments_count' => $data['comments_count'],
                    'is_story' => $data['is_story'],
                    'insights_available' => true,
                ]
            );

            $media->refresh();

            try {
                if ($command && ($index + 1) % 10 === 0) {
                    $command->line("     Verarbeite Media " . ($index + 1) . "/{$totalCount}...");
                }

                $this->downloadMediaFiles($media, $data, $command);
            } catch (\Exception $e) {
                if ($command) {
                    $command->error("     Fehler beim Download von Media {$data['media_id']}: {$e->getMessage()}");
                }
                Log::error('SjInstagramMediaService: Error downloading media', [
                    'media_id' => $media->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $syncedMedia[] = $media;
        }

        // Gelöschte Media entfernen
        $existingIds = SjInstagramMedia::where('instagram_account_id', $account->id)
            ->pluck('external_id')
            ->toArray();

        $deletedIds = array_diff($existingIds, $retrievedIds);

        if (!empty($deletedIds)) {
            $deletedCount = SjInstagramMedia::where('instagram_account_id', $account->id)
                ->whereIn('external_id', $deletedIds)
                ->delete();

            if ($command) {
                $command->info("     {$deletedCount} gelöschte Media-Item(s) entfernt");
            }
        }

        return $syncedMedia;
    }

    protected function downloadMediaFiles(SjInstagramMedia $media, array $mediaData, ?\Illuminate\Console\Command $command = null): void
    {
        $contextType = SjInstagramMedia::class;
        $contextId = $media->id;

        // Primary media
        if (!empty($mediaData['media_url'])) {
            $existing = ContextFile::where('context_type', $contextType)
                ->where('context_id', $contextId)
                ->whereJsonContains('meta->role', 'primary')
                ->first();

            if (!$existing) {
                $this->mediaDownloadService->downloadAndStore(
                    $mediaData['media_url'],
                    $contextType,
                    $contextId,
                    [
                        'media_type' => $mediaData['media_type'],
                        'role' => 'primary',
                        'generate_variants' => false,
                    ],
                    $command
                );
            }
        }

        // Thumbnail
        if (!empty($mediaData['thumbnail_url']) && $mediaData['thumbnail_url'] !== $mediaData['media_url']) {
            $existingThumb = ContextFile::where('context_type', $contextType)
                ->where('context_id', $contextId)
                ->whereJsonContains('meta->role', 'thumbnail')
                ->first();

            if (!$existingThumb) {
                $this->mediaDownloadService->downloadAndStore(
                    $mediaData['thumbnail_url'],
                    $contextType,
                    $contextId,
                    [
                        'media_type' => 'thumbnail',
                        'role' => 'thumbnail',
                        'generate_variants' => false,
                    ],
                    $command
                );
            }
        }

        // Carousel children
        if (!empty($mediaData['children'])) {
            foreach ($mediaData['children'] as $index => $child) {
                if (!empty($child['media_url'])) {
                    $existingChild = ContextFile::where('context_type', $contextType)
                        ->where('context_id', $contextId)
                        ->whereJsonContains('meta->role', 'carousel')
                        ->whereJsonContains('meta->carousel_index', $index)
                        ->first();

                    if (!$existingChild) {
                        $this->mediaDownloadService->downloadAndStore(
                            $child['media_url'],
                            $contextType,
                            $contextId,
                            [
                                'media_type' => $child['media_type'] ?? 'image',
                                'role' => 'carousel',
                                'carousel_index' => $index,
                                'generate_variants' => false,
                            ],
                            $command
                        );
                    }
                }
            }
        }
    }

    protected function normalizeMediaData(array $mediaData, bool $isStory = false): array
    {
        return [
            'media_id' => $mediaData['id'],
            'caption' => $mediaData['caption'] ?? null,
            'media_type' => $mediaData['media_type'],
            'media_url' => $mediaData['media_url'] ?? null,
            'permalink' => $mediaData['permalink'] ?? null,
            'thumbnail_url' => $mediaData['thumbnail_url'] ?? null,
            'timestamp' => isset($mediaData['timestamp'])
                ? Carbon::parse($mediaData['timestamp'])->format('Y-m-d H:i:s')
                : null,
            'like_count' => $mediaData['like_count'] ?? 0,
            'comments_count' => $mediaData['comments_count'] ?? 0,
            'is_story' => $isStory,
            'children' => $mediaData['children']['data'] ?? [],
        ];
    }
}
