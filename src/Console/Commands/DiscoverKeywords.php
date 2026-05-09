<?php

namespace Platform\Syltjunkie\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Integrations\DTOs\DataForSeo\LabsKeywordResult;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Integrations\Services\DataForSeoIntegrationService;
use Platform\Syltjunkie\Models\SjKeyword;
use Platform\Syltjunkie\Models\SjKeywordRanking;
use Platform\Syltjunkie\Models\SjTrendSignal;

class DiscoverKeywords extends Command
{
    protected $signature = 'syltjunkie:discover-keywords
                            {--max-seeds= : Wie viele Seeds verarbeiten (default: alle)}
                            {--detect-opportunities : Keyword-Opportunity Signals erstellen}
                            {--dry-run : Nur anzeigen was passieren würde}';

    protected $description = 'Expand seed keywords via DataForSEO Labs API, store with intent/topic, detect volume spikes & opportunities';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $detectOpportunities = $this->option('detect-opportunities');
        $maxSeeds = $this->option('max-seeds') ? (int) $this->option('max-seeds') : null;

        $config = config('syltjunkie.keyword_discovery');
        $seedKeywords = $config['seed_keywords'] ?? [];
        $minVolume = $config['min_volume'] ?? 50;
        $maxPerSeed = $config['max_keywords_per_seed'] ?? 50;
        $intentPatterns = $config['intent_patterns'] ?? [];
        $topicPatterns = $config['topic_patterns'] ?? [];
        $opportunityMinVolume = $config['opportunity_min_volume'] ?? 200;

        if ($maxSeeds !== null) {
            $seedKeywords = array_slice($seedKeywords, 0, $maxSeeds);
        }

        $this->info('Syltjunkie Keyword Discovery');
        $this->info('============================');
        $this->info('Seeds: ' . count($seedKeywords) . ' | Min Volume: ' . $minVolume . ' | Max/Seed: ' . $maxPerSeed);
        if ($dryRun) {
            $this->warn('DRY-RUN Modus — keine API-Calls');
        }
        $this->newLine();

        if ($dryRun) {
            $this->table(['#', 'Seed Keyword'], collect($seedKeywords)->map(fn($s, $i) => [$i + 1, $s])->toArray());
            $this->newLine();
            $estimatedCost = count($seedKeywords) * 0.01;
            $this->info("Geplant: " . count($seedKeywords) . " Labs-API-Calls (~\${$estimatedCost})");
            return self::SUCCESS;
        }

        $teams = $this->resolveTeams();
        if ($teams->isEmpty()) {
            $this->warn('Keine Teams mit aktiven Syltjunkie-Entities gefunden.');
            return self::SUCCESS;
        }

        $totalNew = 0;
        $totalUpdated = 0;
        $totalSpikes = 0;
        $totalOpportunities = 0;

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

            $api = app(DataForSeoApiService::class);
            $today = Carbon::today();

            foreach ($seedKeywords as $seed) {
                $this->line("  Seed: \"{$seed}\"");

                try {
                    $results = $api->getLabsKeywordSuggestions($user, [$seed], limit: $maxPerSeed);
                } catch (\Exception $e) {
                    $this->error("    API-Fehler: {$e->getMessage()}");
                    continue;
                }

                $filtered = array_filter($results, fn(LabsKeywordResult $r) => ($r->searchVolume ?? 0) >= $minVolume);
                $this->line("    " . count($results) . " Ergebnisse, " . count($filtered) . " mit Volume >= {$minVolume}");

                foreach ($filtered as $result) {
                    $keywordLower = strtolower(trim($result->keyword));
                    if (empty($keywordLower)) {
                        continue;
                    }

                    $existing = SjKeyword::where('team_id', $teamId)->where('keyword', $keywordLower)->first();
                    $previousVolume = $existing?->search_volume;

                    // Compute monthly volumes & seasonality
                    $monthlyVolumes = null;
                    $peakMonth = null;
                    $seasonalityIndex = null;

                    if ($result->monthlySearches && count($result->monthlySearches) >= 6) {
                        $byMonth = [];
                        foreach ($result->monthlySearches as $m) {
                            $month = $m['month'] ?? 0;
                            if ($month >= 1 && $month <= 12) {
                                $byMonth[$month] = $m['search_volume'];
                            }
                        }
                        if (count($byMonth) >= 6) {
                            $monthlyVolumes = $byMonth;
                            $peakMonth = array_search(max($byMonth), $byMonth);
                            $avg = array_sum($byMonth) / count($byMonth);
                            $seasonalityIndex = $avg > 0 ? round(max($byMonth) / $avg, 2) : null;
                        }
                    }

                    // Derive intent and topic
                    $intent = $this->deriveIntent($keywordLower, $intentPatterns);
                    $topic = $this->deriveTopic($keywordLower, $topicPatterns);

                    $updateData = array_filter([
                        'search_volume' => $result->searchVolume,
                        'cpc_cents' => $result->cpc !== null ? (int) round($result->cpc * 100) : null,
                        'competition' => $result->competition,
                        'keyword_difficulty' => $result->keywordDifficulty,
                        'monthly_volumes' => $monthlyVolumes,
                        'peak_month' => $peakMonth,
                        'seasonality_index' => $seasonalityIndex,
                        'last_fetched_at' => now(),
                    ], fn($v) => $v !== null);

                    // Only set intent/topic if not already set
                    if ($intent && !$existing?->search_intent) {
                        $updateData['search_intent'] = $intent;
                    }
                    if ($topic && !$existing?->topic) {
                        $updateData['topic'] = $topic;
                    }

                    SjKeyword::updateOrCreate(
                        ['team_id' => $teamId, 'keyword' => $keywordLower],
                        $updateData
                    );

                    if ($existing) {
                        $totalUpdated++;
                    } else {
                        $totalNew++;
                    }

                    // Volume spike detection
                    if ($previousVolume !== null && $previousVolume > 0 && $result->searchVolume !== null) {
                        $increase = ($result->searchVolume - $previousVolume) / $previousVolume;
                        if ($increase > 1.0 && $result->searchVolume >= $opportunityMinVolume) {
                            $keyword = SjKeyword::where('team_id', $teamId)->where('keyword', $keywordLower)->first();
                            $created = $this->createSignal($teamId, $keyword?->id, [
                                'signal_type' => 'volume_spike',
                                'severity' => 'watch',
                                'title' => "Volume Spike: \"{$keywordLower}\" {$previousVolume} → {$result->searchVolume}",
                                'description' => "Suchvolumen hat sich mehr als verdoppelt (+{$this->formatPercent($increase)}).",
                                'metric_before' => $previousVolume,
                                'metric_after' => $result->searchVolume,
                                'metric_delta' => $result->searchVolume - $previousVolume,
                                'detected_at' => $today,
                            ]);
                            $totalSpikes += $created;
                        }
                    }
                }
            }

            // Keyword opportunity detection
            if ($detectOpportunities) {
                $this->newLine();
                $this->info('  Keyword Opportunity Detection...');

                $opportunities = SjKeyword::where('team_id', $teamId)
                    ->where('search_volume', '>=', $opportunityMinVolume)
                    ->whereDoesntHave('rankings')
                    ->get();

                foreach ($opportunities as $kw) {
                    $created = $this->createSignal($teamId, $kw->id, [
                        'signal_type' => 'keyword_opportunity',
                        'severity' => 'info',
                        'title' => "Keyword Opportunity: \"{$kw->keyword}\" ({$kw->search_volume} Searches)",
                        'description' => "Keyword hat {$kw->search_volume} monatliche Suchen, aber kein Entity rankt dafür.",
                        'metric_before' => 0,
                        'metric_after' => $kw->search_volume,
                        'metric_delta' => $kw->search_volume,
                        'detected_at' => $today,
                    ]);
                    $totalOpportunities += $created;
                }

                $this->info("    {$totalOpportunities} Keyword-Opportunity Signals erstellt");
            }
        }

        $this->newLine();
        $this->info('Zusammenfassung:');
        $this->info("  Neue Keywords: {$totalNew}");
        $this->info("  Aktualisiert: {$totalUpdated}");
        $this->info("  Volume Spikes: {$totalSpikes}");
        if ($detectOpportunities) {
            $this->info("  Opportunities: {$totalOpportunities}");
        }

        return self::SUCCESS;
    }

    protected function deriveIntent(string $keyword, array $intentPatterns): ?string
    {
        foreach ($intentPatterns as $intent => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($keyword, $pattern)) {
                    return $intent;
                }
            }
        }

        return 'informational';
    }

    protected function deriveTopic(string $keyword, array $topicPatterns): ?string
    {
        foreach ($topicPatterns as $topic => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($keyword, $pattern)) {
                    return $topic;
                }
            }
        }

        return null;
    }

    protected function createSignal(int $teamId, ?int $keywordId, array $data): int
    {
        $exists = SjTrendSignal::where('signal_type', $data['signal_type'])
            ->where('keyword_id', $keywordId)
            ->where('detected_at', $data['detected_at'])
            ->exists();

        if ($exists) {
            return 0;
        }

        SjTrendSignal::create(array_merge($data, [
            'team_id' => $teamId,
            'keyword_id' => $keywordId,
        ]));

        return 1;
    }

    protected function formatPercent(float $ratio): string
    {
        return round($ratio * 100) . '%';
    }

    // =========================================================================
    // Team/User resolution (same pattern as FetchEntityData)
    // =========================================================================

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
