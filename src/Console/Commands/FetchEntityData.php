<?php

namespace Platform\Syltjunkie\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Integrations\Services\DataForSeoIntegrationService;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityUrl;
use Platform\Syltjunkie\Models\SjKeyword;
use Platform\Syltjunkie\Models\SjKeywordEntityRelevance;
use Platform\Syltjunkie\Models\SjKeywordRanking;
use Platform\Syltjunkie\Models\SjTrendSignal;
use Platform\Syltjunkie\Models\SjUrlSnapshot;

class FetchEntityData extends Command
{
    protected $signature = 'syltjunkie:fetch-entity-data
                            {--type=all : What to fetch: google_business, rankings, or all}
                            {--max-google=200 : Max entities for Google Business per run}
                            {--max-rankings=50 : Max domains for keyword rankings per run}
                            {--google-interval=3 : Days between Google Business fetches}
                            {--rankings-interval=14 : Days between ranking fetches}
                            {--detect-trends : Run trend detection after fetching}
                            {--dry-run : Show what would be fetched without doing it}';

    protected $description = 'Fetch Google Business + Keyword Rankings with freshness-based rotation and budget caps';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $type = $this->option('type');
        $detectTrends = $this->option('detect-trends');

        $this->info('Syltjunkie Entity Data Fetch');
        $this->info('============================');
        if ($dryRun) {
            $this->warn('DRY-RUN Modus — keine API-Calls');
        }
        $this->newLine();

        // Find all active Syltjunkie teams
        $teams = $this->resolveTeams();
        if ($teams->isEmpty()) {
            $this->warn('Keine Teams mit aktiven Syltjunkie-Entities gefunden.');
            return self::SUCCESS;
        }

        $totalGoogleFetched = 0;
        $totalRankingsFetched = 0;

        foreach ($teams as $team) {
            $user = $this->resolveUserForTeam($team);
            if (!$user) {
                $this->warn("  Team '{$team->name}' (ID: {$team->id}): Kein User mit DataForSEO-Zugang gefunden, überspringe.");
                continue;
            }

            // Check if user has DataForSEO connection
            $integrationService = app(DataForSeoIntegrationService::class);
            $connection = $integrationService->getConnectionForUser($user);
            if (!$connection) {
                $this->warn("  Team '{$team->name}': Keine DataForSEO-Connection, überspringe.");
                continue;
            }

            $this->info("Team: {$team->name} (ID: {$team->id}, User: {$user->email})");

            $rootTeam = $team->getRootTeam();
            $teamId = $rootTeam->id;

            if (in_array($type, ['all', 'google_business'])) {
                $fetched = $this->fetchGoogleBusiness($user, $teamId, $dryRun);
                $totalGoogleFetched += $fetched;
            }

            if (in_array($type, ['all', 'rankings'])) {
                $fetched = $this->fetchRankings($user, $teamId, $dryRun);
                $totalRankingsFetched += $fetched;
            }

            if ($detectTrends && !$dryRun) {
                $this->detectTrends($teamId);
            }
        }

        $this->newLine();
        $this->info('Zusammenfassung:');
        $this->info("  Google Business: {$totalGoogleFetched} Entities");
        $this->info("  Keyword Rankings: {$totalRankingsFetched} Domains");
        $estimatedCost = ($totalGoogleFetched * 0.02) + ($totalRankingsFetched * 0.10);
        $this->info("  Geschätzte Kosten: ~\${$estimatedCost}");

        return self::SUCCESS;
    }

    protected function fetchGoogleBusiness(User $user, int $teamId, bool $dryRun): int
    {
        $maxGoogle = (int) $this->option('max-google');
        $intervalDays = (int) $this->option('google-interval');
        $staleDate = Carbon::now()->subDays($intervalDays);

        // Find entities needing a Google Business refresh
        $entities = SjEntity::where('team_id', $teamId)
            ->where('is_active', true)
            ->whereHas('entityUrls', fn($q) => $q
                ->where('platform', 'google_maps')
                ->where('is_active', true)
                ->where(fn($q2) => $q2
                    ->whereNull('last_checked_at')
                    ->orWhere('last_checked_at', '<', $staleDate)
                )
            )
            ->with(['entityUrls' => fn($q) => $q->where('platform', 'google_maps')->where('is_active', true)])
            ->orderByRaw('(SELECT MIN(COALESCE(last_checked_at, \'1970-01-01\')) FROM sj_entity_urls WHERE sj_entity_urls.entity_id = sj_entities.id AND platform = \'google_maps\') ASC')
            ->limit($maxGoogle)
            ->get();

        if ($entities->isEmpty()) {
            $this->line('  Google Business: alle aktuell, nichts zu tun.');
            return 0;
        }

        $this->info("  Google Business: {$entities->count()} Entities fällig (Interval: {$intervalDays} Tage, Max: {$maxGoogle})");

        if ($dryRun) {
            foreach ($entities->take(5) as $entity) {
                $url = $entity->entityUrls->first();
                $lastChecked = $url?->last_checked_at?->format('d.m.Y') ?? 'nie';
                $this->line("    - {$entity->name} (letzte Prüfung: {$lastChecked})");
            }
            if ($entities->count() > 5) {
                $this->line("    ... und " . ($entities->count() - 5) . " weitere");
            }
            return $entities->count();
        }

        $api = app(DataForSeoApiService::class);
        $today = now()->toDateString();
        $locationCode = config('integrations.dataforseo.default_location_code', 2276);
        $fetched = 0;

        foreach ($entities as $entity) {
            try {
                $googleMapsUrl = $entity->entityUrls->first();
                $placeId = $googleMapsUrl?->google_place_id;

                $ortName = $entity->ortEntity()?->name;
                $keyword = $placeId
                    ? "place_id:{$placeId}"
                    : trim("{$entity->name} {$ortName}");

                $businessResults = $api->getGoogleBusinessInfo($user, $keyword, $locationCode);

                if (empty($businessResults)) {
                    continue;
                }

                $best = $businessResults[0];
                if ($placeId) {
                    foreach ($businessResults as $r) {
                        if ($r->placeId === $placeId) {
                            $best = $r;
                            break;
                        }
                    }
                }

                // Upsert snapshot
                SjUrlSnapshot::updateOrCreate(
                    ['entity_url_id' => $googleMapsUrl->id, 'captured_at' => $today],
                    [
                        'team_id' => $teamId,
                        'review_count' => $best->ratingVotesCount,
                        'average_rating' => $best->ratingValue,
                        'raw_response' => $best->toArray(),
                    ]
                );

                // Update extra fields
                $extraFields = $entity->extra_fields ?? [];
                $extraFields['google_is_claimed'] = $best->isClaimed;
                $extraFields['google_category'] = $best->category;
                $extraFields['google_current_status'] = $best->currentStatus;
                $entity->update(['extra_fields' => $extraFields]);

                $googleMapsUrl->update(['last_checked_at' => now()]);
                $fetched++;
            } catch (\Exception $e) {
                $this->error("    Fehler bei '{$entity->name}': {$e->getMessage()}");
            }
        }

        $this->info("    {$fetched}/{$entities->count()} erfolgreich abgerufen");
        return $fetched;
    }

    protected function fetchRankings(User $user, int $teamId, bool $dryRun): int
    {
        $maxRankings = (int) $this->option('max-rankings');
        $intervalDays = (int) $this->option('rankings-interval');
        $staleDate = Carbon::now()->subDays($intervalDays);

        // Find website entity URLs needing ranking refresh
        $urls = SjEntityUrl::where('team_id', $teamId)
            ->where('platform', 'website')
            ->where('is_active', true)
            ->where(fn($q) => $q
                ->whereNull('last_checked_at')
                ->orWhere('last_checked_at', '<', $staleDate)
            )
            ->with('entity:id,name,slug,entity_type_id')
            ->orderByRaw('COALESCE(last_checked_at, \'1970-01-01\') ASC')
            ->get();

        if ($urls->isEmpty()) {
            $this->line('  Keyword Rankings: alle aktuell, nichts zu tun.');
            return 0;
        }

        // Group by domain — one API call per domain
        $byDomain = [];
        foreach ($urls as $url) {
            $domain = $this->extractDomain($url->url);
            if ($domain) {
                $byDomain[$domain][] = $url;
            }
        }

        // Cap at max domains
        $byDomain = array_slice($byDomain, 0, $maxRankings, true);

        $this->info("  Keyword Rankings: " . count($byDomain) . " Domains fällig (Interval: {$intervalDays} Tage, Max: {$maxRankings})");

        if ($dryRun) {
            foreach (array_slice(array_keys($byDomain), 0, 5) as $domain) {
                $urlCount = count($byDomain[$domain]);
                $lastChecked = $byDomain[$domain][0]->last_checked_at?->format('d.m.Y') ?? 'nie';
                $this->line("    - {$domain} ({$urlCount} URLs, letzte Prüfung: {$lastChecked})");
            }
            if (count($byDomain) > 5) {
                $this->line("    ... und " . (count($byDomain) - 5) . " weitere");
            }
            return count($byDomain);
        }

        $api = app(DataForSeoApiService::class);
        $today = now()->toDateString();
        $fetched = 0;
        $keywordsLimit = 100;

        foreach ($byDomain as $domain => $domainUrls) {
            try {
                $rankedResults = $api->getRankedKeywords($user, $domain, null, null, $keywordsLimit);
                $fetched++;

                // Phase 1: Upsert keywords
                $keywordModels = $this->upsertKeywords($teamId, $rankedResults);

                // Phase 2: Rankings + Attribution
                $urlPaths = [];
                foreach ($domainUrls as $eu) {
                    $path = parse_url($eu->url, PHP_URL_PATH) ?: '/';
                    $urlPaths[$eu->id] = rtrim(strtolower($path), '/');
                }

                foreach ($rankedResults as $rk) {
                    $keywordLower = strtolower(trim($rk->keyword));
                    $keywordModel = $keywordModels[$keywordLower] ?? null;
                    if (!$keywordModel) continue;

                    $rankedPath = rtrim(strtolower(parse_url($rk->url ?? '', PHP_URL_PATH) ?: '/'), '/');
                    $matchedUrlId = $this->findBestPathMatch($rankedPath, $urlPaths);

                    if ($matchedUrlId) {
                        $this->upsertRanking($keywordModel->id, $matchedUrlId, $rk, $today);
                    }
                }

                // Phase 3: Snapshots + last_checked_at
                foreach ($domainUrls as $entityUrl) {
                    $matchedKeywords = 0;
                    $traffic = 0;
                    $valueCents = 0;
                    $entityPath = $urlPaths[$entityUrl->id];

                    foreach ($rankedResults as $rk) {
                        $rankedPath = rtrim(strtolower(parse_url($rk->url ?? '', PHP_URL_PATH) ?: '/'), '/');
                        if ($rankedPath === $entityPath || str_starts_with($rankedPath, $entityPath . '/')) {
                            $matchedKeywords++;
                            $ctr = $this->estimateCtr($rk->position);
                            $traffic += (int) round(($rk->searchVolume ?? 0) * $ctr);
                            $valueCents += (int) round(($rk->searchVolume ?? 0) * $ctr * ($rk->cpc ?? 0) * 100);
                        }
                    }

                    SjUrlSnapshot::updateOrCreate(
                        ['entity_url_id' => $entityUrl->id, 'captured_at' => $today],
                        [
                            'team_id' => $teamId,
                            'keywords_count' => $matchedKeywords,
                            'organic_traffic_estimate' => $traffic ?: null,
                            'organic_value_cents' => $valueCents ?: null,
                            'raw_response' => [
                                'source' => 'ranked_keywords',
                                'domain' => $domain,
                                'domain_total_keywords' => count($rankedResults),
                            ],
                        ]
                    );

                    $entityUrl->update(['last_checked_at' => now()]);
                }
            } catch (\Exception $e) {
                $this->error("    Fehler bei Domain '{$domain}': {$e->getMessage()}");
            }
        }

        $this->info("    {$fetched}/" . count($byDomain) . " Domains erfolgreich abgerufen");
        return $fetched;
    }

    protected function detectTrends(int $teamId): void
    {
        $this->info('  Trend Detection...');

        $today = Carbon::today();
        $entities = SjEntity::where('team_id', $teamId)
            ->where('is_active', true)
            ->with(['entityUrls' => fn($q) => $q->where('is_active', true)])
            ->get();

        $signalsCreated = 0;

        foreach ($entities as $entity) {
            foreach ($entity->entityUrls as $entityUrl) {
                $snapshots = SjUrlSnapshot::where('entity_url_id', $entityUrl->id)
                    ->orderByDesc('captured_at')
                    ->limit(2)
                    ->get();

                if ($snapshots->count() < 2) continue;

                $latest = $snapshots[0];
                $previous = $snapshots[1];

                // Rating Drop
                if ($latest->average_rating !== null && $previous->average_rating !== null) {
                    $delta = (float) $latest->average_rating - (float) $previous->average_rating;
                    if ($delta < -0.2) {
                        $signalsCreated += $this->createSignal($teamId, $entity->id, $entityUrl->id, null, [
                            'signal_type' => 'rating_drop',
                            'severity' => 'action',
                            'title' => "Rating gefallen: {$previous->average_rating} → {$latest->average_rating}",
                            'metric_before' => (float) $previous->average_rating,
                            'metric_after' => (float) $latest->average_rating,
                            'metric_delta' => $delta,
                            'detected_at' => $today,
                        ]);
                    }
                }

                // Review Velocity
                if ($latest->review_count !== null && $previous->review_count !== null) {
                    $reviewDelta = (int) $latest->review_count - (int) $previous->review_count;
                    $daysBetween = max(1, $latest->captured_at->diffInDays($previous->captured_at));
                    $velocity = $reviewDelta / $daysBetween;

                    $allSnapshots = SjUrlSnapshot::where('entity_url_id', $entityUrl->id)
                        ->whereNotNull('review_count')
                        ->orderBy('captured_at')
                        ->pluck('review_count', 'captured_at');

                    $historicalVelocity = 0;
                    if ($allSnapshots->count() >= 3) {
                        $values = $allSnapshots->values();
                        $totalDelta = $values->last() - $values->first();
                        $totalDays = max(1, Carbon::parse($allSnapshots->keys()->first())->diffInDays(Carbon::parse($allSnapshots->keys()->last())));
                        $historicalVelocity = $totalDelta / $totalDays;
                    }

                    if ($historicalVelocity > 0 && $velocity > $historicalVelocity * 2) {
                        $signalsCreated += $this->createSignal($teamId, $entity->id, $entityUrl->id, null, [
                            'signal_type' => 'review_velocity',
                            'severity' => 'watch',
                            'title' => "Review-Velocity: {$reviewDelta} neue Reviews in {$daysBetween} Tagen",
                            'metric_before' => round($historicalVelocity, 4),
                            'metric_after' => round($velocity, 4),
                            'metric_delta' => round($velocity - $historicalVelocity, 4),
                            'detected_at' => $today,
                        ]);
                    }
                }

                // Ranking Changes
                $rankings = SjKeywordRanking::where('entity_url_id', $entityUrl->id)
                    ->where('captured_at', $today->toDateString())
                    ->whereNotNull('previous_position')
                    ->with('keyword:id,keyword')
                    ->get();

                foreach ($rankings as $ranking) {
                    $positionDelta = $ranking->position_delta;
                    if ($positionDelta !== null && abs($positionDelta) > 10) {
                        $isImproved = $positionDelta > 0;
                        $signalsCreated += $this->createSignal($teamId, $entity->id, $entityUrl->id, $ranking->keyword_id, [
                            'signal_type' => 'ranking_change',
                            'severity' => $isImproved ? 'info' : 'action',
                            'title' => ($isImproved ? 'Ranking verbessert' : 'Ranking gefallen') . ": \"{$ranking->keyword?->keyword}\" {$ranking->previous_position} → {$ranking->position}",
                            'metric_before' => $ranking->previous_position,
                            'metric_after' => $ranking->position,
                            'metric_delta' => $positionDelta,
                            'detected_at' => $today,
                        ]);
                    }
                }
            }
        }

        $this->info("    {$signalsCreated} neue Trend-Signals erstellt");
    }

    protected function createSignal(int $teamId, int $entityId, int $entityUrlId, ?int $keywordId, array $data): int
    {
        $exists = SjTrendSignal::where('signal_type', $data['signal_type'])
            ->where('entity_id', $entityId)
            ->where('keyword_id', $keywordId)
            ->where('detected_at', $data['detected_at'])
            ->exists();

        if ($exists) return 0;

        SjTrendSignal::create(array_merge($data, [
            'team_id' => $teamId,
            'entity_id' => $entityId,
            'entity_url_id' => $entityUrlId,
            'keyword_id' => $keywordId,
        ]));

        return 1;
    }

    // =========================================================================
    // Keyword/Ranking helpers (subset of FetchUrlSnapshotsTool logic)
    // =========================================================================

    protected function upsertKeywords(int $teamId, array $rankedResults): array
    {
        $models = [];

        foreach ($rankedResults as $rk) {
            $keywordLower = strtolower(trim($rk->keyword));
            if (empty($keywordLower) || isset($models[$keywordLower])) continue;

            $monthlyVolumes = null;
            $peakMonth = null;
            $seasonalityIndex = null;

            if ($rk->monthlySearches && count($rk->monthlySearches) >= 6) {
                $byMonth = [];
                foreach ($rk->monthlySearches as $m) {
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

            $updateData = array_filter([
                'search_volume' => $rk->searchVolume,
                'cpc_cents' => $rk->cpc !== null ? (int) round($rk->cpc * 100) : null,
                'competition' => $rk->competition,
                'keyword_difficulty' => $rk->keywordDifficulty,
                'monthly_volumes' => $monthlyVolumes,
                'peak_month' => $peakMonth,
                'seasonality_index' => $seasonalityIndex,
                'last_fetched_at' => now(),
            ], fn($v) => $v !== null);

            $model = SjKeyword::updateOrCreate(
                ['team_id' => $teamId, 'keyword' => $keywordLower],
                $updateData
            );

            $models[$keywordLower] = $model;
        }

        return $models;
    }

    protected function upsertRanking(int $keywordId, int $entityUrlId, $rk, string $today): void
    {
        $existing = SjKeywordRanking::where('keyword_id', $keywordId)
            ->where('entity_url_id', $entityUrlId)
            ->where('captured_at', $today)
            ->first();

        if ($existing) {
            $existing->update([
                'position' => $rk->position,
                'ranked_url' => $rk->url ?? '',
                'serp_features' => $rk->serpFeatures,
            ]);
            return;
        }

        $previous = SjKeywordRanking::where('keyword_id', $keywordId)
            ->where('entity_url_id', $entityUrlId)
            ->where('captured_at', '<', $today)
            ->orderByDesc('captured_at')
            ->value('position');

        SjKeywordRanking::create([
            'keyword_id' => $keywordId,
            'entity_url_id' => $entityUrlId,
            'position' => $rk->position,
            'previous_position' => $previous,
            'ranked_url' => $rk->url ?? '',
            'captured_at' => $today,
            'serp_features' => $rk->serpFeatures,
        ]);
    }

    protected function findBestPathMatch(string $rankedPath, array $urlPaths): ?int
    {
        $bestMatch = null;
        $bestLength = -1;

        foreach ($urlPaths as $entityUrlId => $entityPath) {
            if ($rankedPath === $entityPath || str_starts_with($rankedPath, $entityPath . '/')) {
                if (strlen($entityPath) > $bestLength) {
                    $bestMatch = $entityUrlId;
                    $bestLength = strlen($entityPath);
                }
            }
        }

        return $bestMatch;
    }

    protected function estimateCtr(int $position): float
    {
        return match (true) {
            $position === 1 => 0.28,
            $position === 2 => 0.15,
            $position === 3 => 0.11,
            $position <= 5 => 0.06,
            $position <= 10 => 0.03,
            $position <= 20 => 0.01,
            default => 0.005,
        };
    }

    protected function extractDomain(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) return null;
        return preg_replace('/^www\./', '', strtolower($host));
    }

    // =========================================================================
    // Team/User resolution
    // =========================================================================

    protected function resolveTeams()
    {
        // Find teams that have active entities
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
        // Prefer team owner, then first user with DataForSEO access
        $owner = User::find($team->user_id);
        if ($owner) {
            $integrationService = app(DataForSeoIntegrationService::class);
            if ($integrationService->getConnectionForUser($owner)) {
                return $owner;
            }
        }

        // Fallback: any team member with DataForSEO connection
        foreach ($team->users as $user) {
            $integrationService = app(DataForSeoIntegrationService::class);
            if ($integrationService->getConnectionForUser($user)) {
                return $user;
            }
        }

        return null;
    }
}
