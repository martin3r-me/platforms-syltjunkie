<?php

namespace Platform\Syltjunkie\Console\Commands;

use Illuminate\Console\Command;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationsFacebookPage;
use Platform\Integrations\Services\MetaIntegrationService;
use Platform\Syltjunkie\Models\SjChannel;
use Platform\Syltjunkie\Services\SjFacebookPageService;

class SyncFacebookPosts extends Command
{
    protected $signature = 'syltjunkie:sync-facebook-posts
                            {--channel-id= : Specific channel ID to sync}
                            {--limit=100 : Maximum number of posts to fetch}
                            {--dry-run : Show what would be synced without actually doing it}';

    protected $description = 'Synchronize Facebook posts for Syltjunkie channels';

    public function handle(SjFacebookPageService $service): int
    {
        $isDryRun = $this->option('dry-run');
        $channelId = $this->option('channel-id');
        $limit = (int) $this->option('limit');

        if ($isDryRun) {
            $this->info('DRY-RUN Modus');
        }

        $this->info('Starte Facebook Posts Synchronisation...');
        $this->newLine();

        $query = SjChannel::where('type', 'facebook')->where('status', 'active');
        if ($channelId) {
            $query->where('id', $channelId);
        }

        $channels = $query->get();

        if ($channels->isEmpty()) {
            $this->warn('Keine Facebook-Channels gefunden.');
            return self::SUCCESS;
        }

        $this->info("{$channels->count()} Channel(s) gefunden:");
        $this->newLine();

        $metaService = app(MetaIntegrationService::class);
        $syncedCount = 0;
        $skippedCount = 0;

        foreach ($channels as $channel) {
            $this->info("  Channel: '{$channel->name}' (ID: {$channel->id})");

            $connectionId = $channel->integration_connection_id;
            $fbPageId = $channel->facebook_page_id;

            if (!$connectionId || !$fbPageId) {
                $this->warn("     Uebersprungen: Keine Integration Connection oder Facebook Page konfiguriert");
                $skippedCount++;
                continue;
            }

            $connection = IntegrationConnection::find($connectionId);
            $page = IntegrationsFacebookPage::find($fbPageId);

            if (!$connection || !$page) {
                $this->warn("     Uebersprungen: Connection oder Page nicht gefunden");
                $skippedCount++;
                continue;
            }

            $accessToken = $metaService->getValidAccessToken($connection);
            if (!$accessToken) {
                $this->warn("     Uebersprungen: Kein gueltiger Access Token");
                $skippedCount++;
                continue;
            }

            if ($isDryRun) {
                $this->info("     Wuerde Posts synchronisieren (Limit: {$limit})");
                $syncedCount++;
                continue;
            }

            try {
                $result = $service->syncPosts($page, $channel->team_id, $limit, $this);
                $this->info("     " . count($result) . " Post(s) synchronisiert");
                $syncedCount++;
            } catch (\Exception $e) {
                $this->error("     Fehler: {$e->getMessage()}");
                $skippedCount++;
            }
        }

        $this->newLine();
        $this->info("{$syncedCount} Channel(s) synchronisiert, {$skippedCount} uebersprungen");

        return self::SUCCESS;
    }
}
