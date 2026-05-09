<?php

namespace Platform\Syltjunkie\Console;

use Illuminate\Console\Command;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Models\Team;
use Platform\Syltjunkie\Tools\DetectTrendSignalsTool;
use Platform\Syltjunkie\Tools\FetchGoogleBusinessTool;
use Platform\Syltjunkie\Tools\FetchPageSnapshotsTool;
use Platform\Syltjunkie\Tools\FetchUrlSnapshotsTool;

class SnapshotCommand extends Command
{
    protected $signature = 'syltjunkie:snapshot
        {--team= : Team-ID (required)}
        {--user= : User-ID für API-Auth (required)}
        {--google-business : Google Business Profile Daten abrufen}
        {--keywords : Keyword Rankings + URL Snapshots abrufen}
        {--pages : Page Snapshots abrufen}
        {--trends : Trend Signals erkennen}
        {--all : Alle Schritte ausführen}
        {--max-entities=50 : Max Entities pro Schritt}
        {--dry-run : Nur anzeigen, nichts speichern}';

    protected $description = 'Führt Syltjunkie Snapshot-Pipeline aus (Google Business, Keywords, Pages, Trends)';

    public function handle(): int
    {
        $teamId = $this->option('team');
        $userId = $this->option('user');

        if (!$teamId || !$userId) {
            $this->error('--team und --user sind erforderlich.');
            return self::FAILURE;
        }

        $team = Team::find($teamId);
        if (!$team) {
            $this->error("Team {$teamId} nicht gefunden.");
            return self::FAILURE;
        }

        $user = \App\Models\User::find($userId);
        if (!$user) {
            $this->error("User {$userId} nicht gefunden.");
            return self::FAILURE;
        }

        $context = ToolContext::create($user, $team);
        $maxEntities = (int) $this->option('max-entities');
        $dryRun = (bool) $this->option('dry-run');
        $all = (bool) $this->option('all');

        $this->info("Syltjunkie Snapshot — Team: {$team->name} (#{$team->id}), User: {$user->name}");
        if ($dryRun) {
            $this->warn('DRY RUN — keine Daten werden gespeichert.');
        }
        $this->newLine();

        $steps = [];
        if ($all || $this->option('google-business')) {
            $steps[] = ['Google Business', new FetchGoogleBusinessTool(), [
                'team_id' => (int) $teamId,
                'max_entities' => $maxEntities,
                'dry_run' => $dryRun,
            ]];
        }
        if ($all || $this->option('keywords')) {
            $steps[] = ['Keyword Rankings', new FetchUrlSnapshotsTool(), [
                'team_id' => (int) $teamId,
                'max_urls' => $maxEntities,
                'platform' => 'all',
                'dry_run' => $dryRun,
            ]];
        }
        if ($all || $this->option('pages')) {
            $steps[] = ['Page Snapshots', new FetchPageSnapshotsTool(), [
                'team_id' => (int) $teamId,
                'max_urls' => $maxEntities,
                'dry_run' => $dryRun,
            ]];
        }
        if ($all || $this->option('trends')) {
            $steps[] = ['Trend Detection', new DetectTrendSignalsTool(), [
                'team_id' => (int) $teamId,
                'max_entities' => $maxEntities,
                'dry_run' => $dryRun,
            ]];
        }

        if (empty($steps)) {
            $this->warn('Kein Schritt ausgewählt. Nutze --all oder --google-business, --keywords, --pages, --trends.');
            return self::SUCCESS;
        }

        foreach ($steps as [$label, $tool, $args]) {
            $this->info("▶ {$label}...");

            try {
                $result = $tool->execute($args, $context);
                $data = $result->getData();

                if ($result->isSuccess()) {
                    $processed = $data['processed'] ?? $data['entities_analyzed'] ?? $data['total_entities'] ?? '?';
                    $cost = $data['estimated_cost_cents'] ?? null;

                    $this->line("  ✓ Verarbeitet: {$processed}");
                    if ($cost !== null) {
                        $this->line("  $ Geschätzte Kosten: {$cost} Cent");
                    }

                    // Show additional details per step type
                    if (isset($data['signals_created'])) {
                        $this->line("  ↳ Signals erstellt: {$data['signals_created']}, Duplikate übersprungen: {$data['signals_skipped_duplicates']}");
                    }
                    if (isset($data['api_calls_made'])) {
                        $this->line("  ↳ API Calls: {$data['api_calls_made']}");
                    }
                } else {
                    $this->error("  ✗ Fehler: " . ($data['message'] ?? 'Unbekannt'));
                }
            } catch (\Throwable $e) {
                $this->error("  ✗ Exception: {$e->getMessage()}");
            }

            $this->newLine();
        }

        $this->info('Snapshot-Pipeline abgeschlossen.');
        return self::SUCCESS;
    }
}
