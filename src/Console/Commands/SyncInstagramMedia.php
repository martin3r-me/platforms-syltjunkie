<?php

namespace Platform\Syltjunkie\Console\Commands;

use Illuminate\Console\Command;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationsInstagramAccount;
use Platform\Integrations\Services\MetaIntegrationService;
use Platform\Syltjunkie\Models\SjChannel;
use Platform\Syltjunkie\Services\SjInstagramMediaService;

class SyncInstagramMedia extends Command
{
    protected $signature = 'syltjunkie:sync-instagram-media
                            {--channel-id= : Specific channel ID to sync}
                            {--account-id= : Specific Instagram Account ID to sync}
                            {--limit=1000 : Maximum number of media items to fetch}
                            {--dry-run : Show what would be synced without actually doing it}';

    protected $description = 'Synchronize Instagram media for Syltjunkie channels';

    public function handle(SjInstagramMediaService $service): int
    {
        $isDryRun = $this->option('dry-run');
        $channelId = $this->option('channel-id');
        $accountId = $this->option('account-id');
        $limit = (int) $this->option('limit');

        if ($isDryRun) {
            $this->info('DRY-RUN Modus');
        }

        $this->info('Starte Instagram Media Synchronisation...');
        $this->newLine();

        $channels = $this->resolveChannels($channelId, $accountId);

        if ($channels->isEmpty()) {
            $this->warn('Keine Instagram-Channels gefunden.');
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
            $igAccountId = $channel->instagram_account_id;

            if (!$connectionId || !$igAccountId) {
                $this->warn("     Uebersprungen: Keine Integration Connection oder Instagram Account konfiguriert");
                $skippedCount++;
                continue;
            }

            $connection = IntegrationConnection::find($connectionId);
            $account = IntegrationsInstagramAccount::find($igAccountId);

            if (!$connection || !$account) {
                $this->warn("     Uebersprungen: Connection oder Account nicht gefunden");
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
                $this->info("     Wuerde Media synchronisieren (Limit: {$limit})");
                $syncedCount++;
                continue;
            }

            try {
                $result = $service->syncMedia($account, $connection, $channel->team_id, $limit, $this);
                $this->info("     " . count($result) . " Media-Item(s) synchronisiert");
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

    protected function resolveChannels(?string $channelId, ?string $accountId)
    {
        $query = SjChannel::where('type', 'instagram')->where('status', 'active');

        if ($channelId) {
            $query->where('id', $channelId);
        } elseif ($accountId) {
            $query->whereJsonContains('config->instagram_account_id', (int) $accountId);
        }

        return $query->get();
    }
}
