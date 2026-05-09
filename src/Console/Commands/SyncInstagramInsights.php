<?php

namespace Platform\Syltjunkie\Console\Commands;

use Illuminate\Console\Command;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationsInstagramAccount;
use Platform\Integrations\Services\MetaIntegrationService;
use Platform\Syltjunkie\Models\SjChannel;
use Platform\Syltjunkie\Services\SjInstagramInsightsService;

class SyncInstagramInsights extends Command
{
    protected $signature = 'syltjunkie:sync-instagram-insights
                            {--channel-id= : Specific channel ID to sync}
                            {--account-id= : Specific Instagram Account ID to sync}
                            {--media-only : Only sync media insights}
                            {--account-only : Only sync account insights}
                            {--dry-run : Show what would be synced without actually doing it}';

    protected $description = 'Synchronize Instagram insights for Syltjunkie channels';

    public function handle(SjInstagramInsightsService $service): int
    {
        $isDryRun = $this->option('dry-run');
        $channelId = $this->option('channel-id');
        $accountId = $this->option('account-id');
        $mediaOnly = $this->option('media-only');
        $accountOnly = $this->option('account-only');

        if ($isDryRun) {
            $this->info('DRY-RUN Modus');
        }

        $this->info('Starte Instagram Insights Synchronisation...');
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
                $this->info("     Wuerde Insights synchronisieren");
                $syncedCount++;
                continue;
            }

            try {
                if (!$mediaOnly) {
                    $this->info("     Synchronisiere Account Insights...");
                    $service->syncAccountInsights($account, $connection);
                    $this->info("     Account Insights synchronisiert");
                }

                if (!$accountOnly) {
                    $this->info("     Synchronisiere Media Insights...");
                    $mediaResults = $service->syncMediaInsights($account, $connection);
                    $this->info("     {$mediaResults['synced']} Media Insights synchronisiert, {$mediaResults['skipped']} uebersprungen");
                }

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
