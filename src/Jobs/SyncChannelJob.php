<?php

namespace Platform\Syltjunkie\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationsInstagramAccount;
use Platform\Integrations\Models\IntegrationsFacebookPage;
use Platform\Syltjunkie\Models\SjChannel;
use Platform\Syltjunkie\Services\SjFacebookPageService;
use Platform\Syltjunkie\Services\SjInstagramInsightsService;
use Platform\Syltjunkie\Services\SjInstagramMediaService;

class SyncChannelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public SjChannel $channel,
    ) {}

    public function handle(): void
    {
        $this->channel->update(['sync_status' => 'syncing', 'sync_error' => null]);

        try {
            match ($this->channel->type) {
                'instagram' => $this->syncInstagram(),
                'facebook' => $this->syncFacebook(),
                default => null,
            };

            $this->channel->update([
                'sync_status' => 'completed',
                'last_synced_at' => now(),
            ]);
        } catch (\Exception $e) {
            $this->channel->update([
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
            ]);
        }
    }

    protected function syncInstagram(): void
    {
        $connection = IntegrationConnection::find($this->channel->integration_connection_id);
        $account = IntegrationsInstagramAccount::find($this->channel->instagram_account_id);

        if (!$connection || !$account) {
            throw new \RuntimeException('Integration Connection oder Instagram Account nicht gefunden.');
        }

        $mediaService = app(SjInstagramMediaService::class);
        $mediaService->syncMedia($account, $connection, $this->channel->team_id);

        $insightsService = app(SjInstagramInsightsService::class);
        $insightsService->syncAccountInsights($account, $connection);
        $insightsService->syncMediaInsights($account, $connection);
    }

    protected function syncFacebook(): void
    {
        $page = IntegrationsFacebookPage::find($this->channel->facebook_page_id);

        if (!$page) {
            throw new \RuntimeException('Facebook Page nicht gefunden.');
        }

        $service = app(SjFacebookPageService::class);
        $service->syncPosts($page, $this->channel->team_id);
    }
}
