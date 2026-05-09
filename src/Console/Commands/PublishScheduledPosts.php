<?php

namespace Platform\Syltjunkie\Console\Commands;

use Illuminate\Console\Command;
use Platform\Syltjunkie\Models\SjChannelPost;
use Platform\Syltjunkie\Services\SjPublishingService;

class PublishScheduledPosts extends Command
{
    protected $signature = 'syltjunkie:publish-scheduled';
    protected $description = 'Publish scheduled posts that are due';

    public function handle(SjPublishingService $publishingService): int
    {
        $posts = SjChannelPost::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->with('channel')
            ->get();

        if ($posts->isEmpty()) {
            $this->info('No scheduled posts due.');
            return self::SUCCESS;
        }

        $this->info("Publishing {$posts->count()} scheduled post(s)...");

        foreach ($posts as $post) {
            $result = $publishingService->publish($post);

            if ($result['success']) {
                $this->info("  Post #{$post->id} published successfully.");
            } else {
                $this->error("  Post #{$post->id} failed: {$result['error']}");
            }
        }

        return self::SUCCESS;
    }
}
