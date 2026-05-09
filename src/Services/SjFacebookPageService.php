<?php

namespace Platform\Syltjunkie\Services;

use Illuminate\Support\Facades\Log;
use Platform\Core\Models\ContextFile;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationsFacebookPage;
use Platform\Integrations\Services\IntegrationsFacebookPageService;
use Platform\Syltjunkie\Models\SjFacebookPost;

class SjFacebookPageService
{
    protected IntegrationsFacebookPageService $coreService;
    protected SjMediaDownloadService $mediaDownloadService;

    public function __construct(IntegrationsFacebookPageService $coreService, SjMediaDownloadService $mediaDownloadService)
    {
        $this->coreService = $coreService;
        $this->mediaDownloadService = $mediaDownloadService;
    }

    public function syncPosts(IntegrationsFacebookPage $page, int $teamId, int $limit = 100, ?\Illuminate\Console\Command $command = null): array
    {
        $postsData = $this->coreService->fetchFacebookPosts($page, $limit);

        $syncedPosts = [];
        $retrievedIds = [];

        $totalCount = count($postsData);
        if ($command) {
            $command->info("     {$totalCount} Facebook-Post(s) gefunden");
        }

        foreach ($postsData as $index => $data) {
            $retrievedIds[] = $data['external_id'];

            $post = SjFacebookPost::updateOrCreate(
                [
                    'external_id' => $data['external_id'],
                    'facebook_page_id' => $page->id,
                ],
                [
                    'team_id' => $teamId,
                    'message' => $data['message'],
                    'media_url' => $data['media_url'],
                    'permalink_url' => $data['permalink_url'],
                    'published_at' => $data['published_at'],
                ]
            );

            $post->refresh();

            // Download media
            if ($data['media_url']) {
                try {
                    $existing = ContextFile::where('context_type', SjFacebookPost::class)
                        ->where('context_id', $post->id)
                        ->whereJsonContains('meta->role', 'primary')
                        ->first();

                    if (!$existing) {
                        $this->mediaDownloadService->downloadAndStore(
                            $data['media_url'],
                            SjFacebookPost::class,
                            $post->id,
                            [
                                'role' => 'primary',
                                'generate_variants' => true,
                            ],
                            $command
                        );
                    }
                } catch (\Exception $e) {
                    Log::error('SjFacebookPageService: Error downloading media', [
                        'post_id' => $post->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($command && ($index + 1) % 10 === 0) {
                $command->line("     Verarbeite Post " . ($index + 1) . "/{$totalCount}...");
            }

            $syncedPosts[] = $post;
        }

        // Delete removed posts
        $existingIds = SjFacebookPost::where('facebook_page_id', $page->id)
            ->pluck('external_id')
            ->toArray();

        $deletedIds = array_diff($existingIds, $retrievedIds);

        if (!empty($deletedIds)) {
            $deletedCount = SjFacebookPost::where('facebook_page_id', $page->id)
                ->whereIn('external_id', $deletedIds)
                ->delete();

            if ($command) {
                $command->info("     {$deletedCount} geloeschte Post(s) entfernt");
            }
        }

        return $syncedPosts;
    }
}
