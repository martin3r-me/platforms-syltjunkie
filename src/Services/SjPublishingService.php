<?php

namespace Platform\Syltjunkie\Services;

use Illuminate\Support\Facades\Log;
use Platform\Syltjunkie\Contracts\ChannelPublisherContract;
use Platform\Syltjunkie\Models\SjChannel;
use Platform\Syltjunkie\Models\SjChannelPost;
use Platform\Syltjunkie\Services\Publishers\InstagramPublisher;

class SjPublishingService
{
    public function publish(SjChannelPost $post): array
    {
        $channel = $post->channel;

        $post->update(['status' => 'publishing']);

        try {
            $publisher = $this->resolvePublisher($channel);

            if (!$publisher) {
                $post->update([
                    'status' => 'failed',
                    'error_message' => "Kein Publisher für Channel-Typ '{$channel->type}' verfügbar.",
                ]);

                return [
                    'success' => false,
                    'external_post_id' => null,
                    'error' => "Kein Publisher für Channel-Typ '{$channel->type}' verfügbar.",
                ];
            }

            $result = $publisher->publish($channel, $post);

            if ($result['success']) {
                $post->update([
                    'status' => 'published',
                    'published_at' => now(),
                    'external_post_id' => $result['external_post_id'],
                    'error_message' => null,
                ]);
            } else {
                $post->update([
                    'status' => 'failed',
                    'error_message' => $result['error'],
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('SjPublishingService: publish exception', [
                'post_id' => $post->id,
                'channel_id' => $channel->id,
                'error' => $e->getMessage(),
            ]);

            $post->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'external_post_id' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function resolvePublisher(SjChannel $channel): ?ChannelPublisherContract
    {
        return match ($channel->type) {
            'instagram' => app(InstagramPublisher::class),
            default => null,
        };
    }
}
