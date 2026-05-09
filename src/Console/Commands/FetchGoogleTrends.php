<?php

namespace Platform\Syltjunkie\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Integrations\Services\DataForSeoIntegrationService;
use Platform\Syltjunkie\Models\SjKeyword;
use Platform\Syltjunkie\Models\SjTrendSignal;

class FetchGoogleTrends extends Command
{
    protected $signature = 'syltjunkie:fetch-google-trends
                            {--max-keywords= : Wie viele Keywords verarbeiten}
                            {--min-volume=200 : Mindest-Suchvolumen für Trends-Fetch}
                            {--dry-run : Nur anzeigen}';

    protected $description = 'Fetch Google Trends data for discovered keywords via DataForSEO';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $maxKeywords = $this->option('max-keywords') ? (int) $this->option('max-keywords') : null;
        $minVolume = (int) $this->option('min-volume');

        $config = config('syltjunkie.keyword_discovery.google_trends', []);
        $timeRange = $config['time_range'] ?? 'past_12_months';
        $surgeThreshold = $config['surge_threshold'] ?? 0.5;
        $surgeActionThreshold = $config['surge_action_threshold'] ?? 1.0;

        $this->info('Syltjunkie Google Trends Fetch');
        $this->info('==============================');
        $this->info("Min Volume: {$minVolume} | Time Range: {$timeRange}");
        if ($maxKeywords) {
            $this->info("Max Keywords: {$maxKeywords}");
        }
        if ($dryRun) {
            $this->warn('DRY-RUN Modus — keine API-Calls');
        }
        $this->newLine();

        $teams = $this->resolveTeams();
        if ($teams->isEmpty()) {
            $this->warn('Keine Teams mit aktiven Syltjunkie-Entities gefunden.');
            return self::SUCCESS;
        }

        $totalUpdated = 0;
        $totalSurges = 0;

        foreach ($teams as $team) {
            $user = $this->resolveUserForTeam($team);
            if (!$user) {
                $this->warn("Team '{$team->name}' (ID: {$team->id}): Kein User mit DataForSEO-Zugang, überspringe.");
                continue;
            }

            $integrationService = app(DataForSeoIntegrationService::class);
            if (!$integrationService->getConnectionForUser($user)) {
                $this->warn("Team '{$team->name}': Keine DataForSEO-Connection, überspringe.");
                continue;
            }

            $this->info("Team: {$team->name} (ID: {$team->id}, User: {$user->email})");

            $rootTeam = $team->getRootTeam();
            $teamId = $rootTeam->id;

            $query = SjKeyword::where('team_id', $teamId)
                ->where('search_volume', '>=', $minVolume)
                ->orderByRaw('trends_fetched_at IS NULL DESC, trends_fetched_at ASC');

            if ($maxKeywords) {
                $query->limit($maxKeywords);
            }

            $keywords = $query->get();

            if ($dryRun) {
                $this->info("  {$keywords->count()} Keywords würden verarbeitet:");
                $table = $keywords->map(fn($kw) => [
                    $kw->keyword,
                    $kw->search_volume,
                    $kw->trends_fetched_at?->format('d.m.Y') ?? 'nie',
                ])->toArray();
                $this->table(['Keyword', 'Volume', 'Letzte Trends'], $table);

                $batches = ceil($keywords->count() / 5);
                $estimatedCost = $batches * 0.009;
                $this->info("  Geplant: {$batches} API-Calls (~\${$estimatedCost})");
                $this->newLine();
                continue;
            }

            $api = app(DataForSeoApiService::class);
            $today = Carbon::today();
            $batches = $keywords->chunk(5);

            foreach ($batches as $batchIndex => $batch) {
                $keywordStrings = $batch->pluck('keyword')->toArray();
                $this->line("  Batch " . ($batchIndex + 1) . "/" . $batches->count() . ": " . implode(', ', $keywordStrings));

                try {
                    $results = $api->getGoogleTrendsExplore($user, $keywordStrings, timeRange: $timeRange);
                } catch (\Exception $e) {
                    $this->error("    API-Fehler: {$e->getMessage()}");
                    continue;
                }

                foreach ($results as $result) {
                    $kwModel = $batch->first(fn($kw) => strtolower($kw->keyword) === strtolower($result->keyword));
                    if (!$kwModel) {
                        continue;
                    }

                    $kwModel->update([
                        'google_trends_data' => $result->interestOverTime,
                        'trends_average_interest' => $result->averageInterest,
                        'trends_peak_interest' => $result->peakInterest,
                        'trends_fetched_at' => now(),
                    ]);
                    $totalUpdated++;

                    // Trend Surge Detection
                    $surges = $this->detectTrendSurge($result, $surgeThreshold, $surgeActionThreshold);
                    if ($surges) {
                        $severity = $surges['severity'];
                        $increase = $surges['increase'];

                        $exists = SjTrendSignal::where('signal_type', 'trend_surge')
                            ->where('keyword_id', $kwModel->id)
                            ->where('detected_at', $today)
                            ->exists();

                        if (!$exists) {
                            SjTrendSignal::create([
                                'team_id' => $teamId,
                                'keyword_id' => $kwModel->id,
                                'signal_type' => 'trend_surge',
                                'severity' => $severity,
                                'title' => "Trend Surge: \"{$kwModel->keyword}\" (+" . round($increase * 100) . "%)",
                                'description' => "Google Trends Interest letzte 4 Wochen vs. vorherige 4 Wochen: +" . round($increase * 100) . "% Anstieg.",
                                'metric_before' => $surges['before'],
                                'metric_after' => $surges['after'],
                                'metric_delta' => $surges['after'] - $surges['before'],
                                'detected_at' => $today,
                            ]);
                            $totalSurges++;
                            $this->line("    Trend Surge ({$severity}): \"{$kwModel->keyword}\" +" . round($increase * 100) . "%");
                        }
                    }
                }

                // Rate-Limiting: kurze Pause zwischen Batches
                if ($batchIndex < $batches->count() - 1) {
                    usleep(500_000);
                }
            }
        }

        $this->newLine();
        $this->info('Zusammenfassung:');
        $this->info("  Keywords aktualisiert: {$totalUpdated}");
        $this->info("  Trend Surges erkannt: {$totalSurges}");

        return self::SUCCESS;
    }

    protected function detectTrendSurge(
        \Platform\Integrations\DTOs\DataForSeo\GoogleTrendsResult $result,
        float $surgeThreshold,
        float $surgeActionThreshold,
    ): ?array {
        $data = $result->interestOverTime;
        if (!is_array($data) || count($data) < 8) {
            return null;
        }

        // Letzte 4 Datenpunkte vs. vorherige 4 Datenpunkte
        $recent = array_slice($data, -4);
        $previous = array_slice($data, -8, 4);

        $recentValues = array_filter(array_column($recent, 'value'), fn($v) => $v !== null);
        $previousValues = array_filter(array_column($previous, 'value'), fn($v) => $v !== null);

        if (empty($recentValues) || empty($previousValues)) {
            return null;
        }

        $recentAvg = array_sum($recentValues) / count($recentValues);
        $previousAvg = array_sum($previousValues) / count($previousValues);

        if ($previousAvg <= 0) {
            return null;
        }

        $increase = ($recentAvg - $previousAvg) / $previousAvg;

        if ($increase >= $surgeActionThreshold) {
            return ['severity' => 'action', 'increase' => $increase, 'before' => (int) round($previousAvg), 'after' => (int) round($recentAvg)];
        }

        if ($increase >= $surgeThreshold) {
            return ['severity' => 'watch', 'increase' => $increase, 'before' => (int) round($previousAvg), 'after' => (int) round($recentAvg)];
        }

        return null;
    }

    protected function resolveTeams()
    {
        return Team::whereHas('users')
            ->whereIn('id', function ($q) {
                $q->select('team_id')
                    ->from('sj_entities')
                    ->where('is_active', true)
                    ->distinct();
            })
            ->get();
    }

    protected function resolveUserForTeam(Team $team): ?User
    {
        $owner = User::find($team->user_id);
        if ($owner) {
            $integrationService = app(DataForSeoIntegrationService::class);
            if ($integrationService->getConnectionForUser($owner)) {
                return $owner;
            }
        }

        foreach ($team->users as $user) {
            $integrationService = app(DataForSeoIntegrationService::class);
            if ($integrationService->getConnectionForUser($user)) {
                return $user;
            }
        }

        return null;
    }
}
