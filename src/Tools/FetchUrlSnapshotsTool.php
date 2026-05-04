<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\DTOs\DataForSeo\RankedKeywordResult;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Syltjunkie\Models\SjEntityUrl;
use Platform\Syltjunkie\Models\SjKeyword;
use Platform\Syltjunkie\Models\SjKeywordEntityRelevance;
use Platform\Syltjunkie\Models\SjKeywordRanking;
use Platform\Syltjunkie\Models\SjUrlSnapshot;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class FetchUrlSnapshotsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.url_snapshots.FETCH';
    }

    public function getDescription(): string
    {
        return 'POST /syltjunkie/url_snapshots/fetch - Ruft Keyword-Rankings für Entity-URLs via DataForSEO ab. '
            . 'Pro Domain nur 1 API-Call (~$0.10). Keywords werden normalisiert in sj_keywords gespeichert, '
            . 'Rankings in sj_keyword_rankings, Attribution in sj_keyword_entity_relevance. '
            . 'URL-Snapshots werden als Tages-Aggregate berechnet.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                ],
                'entity_url_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Einzelne Entity-URL-ID.',
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Alle URLs einer Entity.',
                ],
                'platform' => [
                    'type' => 'string',
                    'description' => 'Optional: Nur URLs dieser Platform. Default: website.',
                    'enum' => ['website', 'google_maps', 'tripadvisor', 'instagram', 'facebook', 'booking', 'yelp', 'other', 'all'],
                    'default' => 'website',
                ],
                'max_urls' => [
                    'type' => 'integer',
                    'description' => 'Optional: Maximale Anzahl URLs. Default: 10. Max: 50.',
                    'default' => 10,
                ],
                'keywords_limit' => [
                    'type' => 'integer',
                    'description' => 'Optional: Max Keywords pro Domain. Default: 100. Max: 500.',
                    'default' => 100,
                ],
                'dry_run' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Nur anzeigen was passieren würde. Default: false.',
                    'default' => false,
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $maxUrls = min((int) ($arguments['max_urls'] ?? 10), 50);
            $keywordsLimit = min((int) ($arguments['keywords_limit'] ?? 100), 500);
            $dryRun = (bool) ($arguments['dry_run'] ?? false);
            $platformFilter = $arguments['platform'] ?? 'website';

            // URLs ermitteln
            $q = SjEntityUrl::query()
                ->where('team_id', $rootTeamId)
                ->where('is_active', true)
                ->with('entity:id,name,slug,ort,entity_type_id');

            if (!empty($arguments['entity_url_id'])) {
                $q->where('id', (int) $arguments['entity_url_id']);
            } elseif (!empty($arguments['entity_id'])) {
                $q->where('entity_id', (int) $arguments['entity_id']);
            }

            if ($platformFilter !== 'all') {
                $q->where('platform', $platformFilter);
            }

            $urls = $q->limit($maxUrls)->get();

            if ($urls->isEmpty()) {
                return ToolResult::success([
                    'message' => 'Keine URLs zum Verarbeiten gefunden.',
                    'processed' => 0,
                ]);
            }

            // Nach Domain gruppieren
            $byDomain = [];
            foreach ($urls as $entityUrl) {
                $domain = $this->extractDomain($entityUrl->url);
                if (!$domain) {
                    continue;
                }
                $byDomain[$domain][] = $entityUrl;
            }

            if ($dryRun) {
                $domainSummary = [];
                foreach ($byDomain as $domain => $domainUrls) {
                    $domainSummary[] = [
                        'domain' => $domain,
                        'entity_urls' => array_map(fn($u) => [
                            'id' => $u->id,
                            'url' => $u->url,
                            'path' => parse_url($u->url, PHP_URL_PATH) ?: '/',
                            'entity_name' => $u->entity?->name,
                        ], $domainUrls),
                    ];
                }

                return ToolResult::success([
                    'dry_run' => true,
                    'unique_domains' => count($byDomain),
                    'total_urls' => $urls->count(),
                    'domains' => $domainSummary,
                    'estimated_cost_cents' => count($byDomain) * 10,
                ]);
            }

            $api = app(DataForSeoApiService::class);
            $results = [];
            $totalKeywordsUpserted = 0;
            $totalRankingsCreated = 0;
            $totalRelevanceCreated = 0;
            $apiCallsMade = 0;
            $today = now()->toDateString();

            foreach ($byDomain as $domain => $domainUrls) {
                try {
                    // 1 API-Call pro Domain
                    $rankedResults = $api->getRankedKeywords(
                        $context->user,
                        $domain,
                        null,
                        null,
                        $keywordsLimit
                    );
                    $apiCallsMade++;

                    // Phase 1: Keywords normalisiert upserten
                    $keywordModels = $this->upsertKeywords($rootTeamId, $rankedResults);
                    $totalKeywordsUpserted += count($keywordModels);

                    // Phase 2: Rankings + Attribution pro Entity-URL zuordnen
                    $urlAssignments = $this->assignAndStoreRankings(
                        $rootTeamId, $today, $domain, $rankedResults, $keywordModels, $domainUrls
                    );

                    // Phase 3: Snapshots als Tages-Aggregate berechnen
                    foreach ($domainUrls as $entityUrl) {
                        $assignment = $urlAssignments[$entityUrl->id];

                        $this->upsertSnapshot($rootTeamId, $entityUrl, $today, $assignment, $domain, count($rankedResults));

                        $entityUrl->update(['last_checked_at' => now()]);

                        $totalRankingsCreated += $assignment['rankings_created'];
                        $totalRelevanceCreated += $assignment['relevance_created'];

                        $results[] = [
                            'entity_url_id' => $entityUrl->id,
                            'url' => $entityUrl->url,
                            'domain' => $domain,
                            'entity_name' => $entityUrl->entity?->name,
                            'keywords_matched' => $assignment['keywords_count'],
                            'keywords_brand' => $assignment['brand_keywords_count'],
                            'organic_traffic' => $assignment['traffic'],
                            'organic_value_cents' => $assignment['value_cents'],
                            'domain_total_keywords' => count($rankedResults),
                        ];
                    }
                } catch (\Throwable $e) {
                    foreach ($domainUrls as $entityUrl) {
                        $results[] = [
                            'entity_url_id' => $entityUrl->id,
                            'url' => $entityUrl->url,
                            'domain' => $domain,
                            'entity_name' => $entityUrl->entity?->name,
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            }

            return ToolResult::success([
                'processed' => count($results),
                'api_calls_made' => $apiCallsMade,
                'unique_domains' => count($byDomain),
                'keywords_upserted' => $totalKeywordsUpserted,
                'rankings_created' => $totalRankingsCreated,
                'relevance_created' => $totalRelevanceCreated,
                'estimated_cost_cents' => $apiCallsMade * 10,
                'urls' => $results,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // Phase 1: Keywords normalisiert upserten
    // =========================================================================

    /**
     * Upserted Keywords in sj_keywords (team-weit dedupliziert).
     *
     * @param RankedKeywordResult[] $rankedResults
     * @return array<string, SjKeyword> keyword_lower => Model
     */
    protected function upsertKeywords(int $teamId, array $rankedResults): array
    {
        $models = [];

        foreach ($rankedResults as $rk) {
            $keywordLower = strtolower(trim($rk->keyword));
            if (empty($keywordLower) || isset($models[$keywordLower])) {
                continue;
            }

            // Saisonalität berechnen aus monthly_searches
            $monthlyVolumes = null;
            $peakMonth = null;
            $seasonalityIndex = null;

            if ($rk->monthlySearches && count($rk->monthlySearches) >= 6) {
                // Als {1: vol, 2: vol, ...12: vol} speichern (aktuellster Wert pro Monat)
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
                    // Seasonality Index: max / avg (1.0 = flat, >2.0 = highly seasonal)
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

    // =========================================================================
    // Phase 2: Rankings zuordnen + Entity-Relevance berechnen
    // =========================================================================

    /**
     * Ordnet Keywords per URL-Pfad-Match zu, speichert Rankings + Relevance.
     *
     * @param RankedKeywordResult[] $rankedResults
     * @param array<string, SjKeyword> $keywordModels
     * @param SjEntityUrl[] $entityUrls
     * @return array<int, array> entityUrlId => assignment data
     */
    protected function assignAndStoreRankings(
        int $teamId,
        string $today,
        string $domain,
        array $rankedResults,
        array $keywordModels,
        array $entityUrls,
    ): array {
        // Entity-URL Pfade vorbereiten
        $urlPaths = [];
        foreach ($entityUrls as $eu) {
            $path = parse_url($eu->url, PHP_URL_PATH) ?: '/';
            $urlPaths[$eu->id] = rtrim(strtolower($path), '/');
        }

        // Ergebnis-Container
        $assignments = [];
        foreach ($entityUrls as $eu) {
            $assignments[$eu->id] = [
                'keywords_count' => 0,
                'brand_keywords_count' => 0,
                'traffic' => 0,
                'value_cents' => 0,
                'rankings_created' => 0,
                'relevance_created' => 0,
            ];
        }

        foreach ($rankedResults as $rk) {
            $keywordLower = strtolower(trim($rk->keyword));
            $keywordModel = $keywordModels[$keywordLower] ?? null;
            if (!$keywordModel) {
                continue;
            }

            $rankedUrl = $rk->url ?? '';
            $rankedPath = rtrim(strtolower(parse_url($rankedUrl, PHP_URL_PATH) ?: '/'), '/');

            // URL-Pfad-Match: welche Entity-URL passt?
            $directMatch = $this->findBestPathMatch($rankedPath, $urlPaths);

            // Brand-Match: enthält das Keyword den Entity-Namen?
            $brandMatches = $this->findBrandMatches($keywordLower, $entityUrls);

            $ctr = ($rk->position) ? $this->estimateCtr($rk->position) : 0;
            $trafficEstimate = ($rk->searchVolume && $rk->position)
                ? (int) round($rk->searchVolume * $ctr)
                : 0;
            $valueCents = ($rk->searchVolume && $rk->position && $rk->cpc)
                ? (int) round($rk->searchVolume * $ctr * $rk->cpc * 100)
                : 0;

            // Direct Ranking: URL-Match → Keyword-Ranking + direct Relevance
            if ($directMatch !== null) {
                $this->upsertRanking($keywordModel->id, $directMatch, $rk, $today);
                $assignments[$directMatch]['rankings_created']++;
                $assignments[$directMatch]['keywords_count']++;
                $assignments[$directMatch]['traffic'] += $trafficEstimate;
                $assignments[$directMatch]['value_cents'] += $valueCents;

                // Direct relevance für die Entity hinter dieser URL
                $entityId = null;
                foreach ($entityUrls as $eu) {
                    if ($eu->id === $directMatch) {
                        $entityId = $eu->entity_id;
                        break;
                    }
                }
                if ($entityId) {
                    $created = $this->upsertRelevance($keywordModel->id, $entityId, 'direct', 1.0, 'auto_ranking');
                    if ($created) {
                        $assignments[$directMatch]['relevance_created']++;
                    }
                }
            }

            // Brand Relevance: Keyword enthält Entity-Name
            foreach ($brandMatches as $match) {
                $entityUrlId = $match['entity_url_id'];
                $entityId = $match['entity_id'];

                // Nicht doppelt als brand + direct zählen
                if ($entityUrlId === $directMatch) {
                    continue;
                }

                $created = $this->upsertRelevance($keywordModel->id, $entityId, 'brand', $match['confidence'], 'auto_brand');
                if ($created) {
                    $assignments[$entityUrlId]['relevance_created']++;
                }
                $assignments[$entityUrlId]['brand_keywords_count']++;
            }
        }

        return $assignments;
    }

    /**
     * Findet die Entity-URL mit dem längsten Pfad-Match.
     */
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

    /**
     * Findet Entities deren Name im Keyword vorkommt (Brand-Match).
     *
     * @return array<array{entity_url_id: int, entity_id: int, confidence: float}>
     */
    protected function findBrandMatches(string $keywordLower, array $entityUrls): array
    {
        $matches = [];

        foreach ($entityUrls as $eu) {
            $entity = $eu->entity;
            if (!$entity) {
                continue;
            }

            $nameLower = strtolower($entity->name);
            $nameWords = array_filter(explode(' ', $nameLower), fn($w) => mb_strlen($w) > 2);

            // Mindestens 2 signifikante Wörter des Entity-Namens müssen im Keyword vorkommen
            $matchedWords = 0;
            foreach ($nameWords as $word) {
                if (str_contains($keywordLower, $word)) {
                    $matchedWords++;
                }
            }

            if (count($nameWords) > 0 && $matchedWords >= min(2, count($nameWords))) {
                $confidence = round($matchedWords / max(count($nameWords), 1), 2);
                $matches[] = [
                    'entity_url_id' => $eu->id,
                    'entity_id' => $entity->id,
                    'confidence' => min($confidence, 0.95),
                ];
            }
        }

        return $matches;
    }

    /**
     * Upserted ein Keyword-Ranking für heute.
     */
    protected function upsertRanking(int $keywordId, int $entityUrlId, RankedKeywordResult $rk, string $today): void
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

        // Vorherige Position ermitteln (letztes Ranking vor heute)
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

    /**
     * Upserted eine Keyword-Entity-Relevance. Returns true wenn neu erstellt.
     */
    protected function upsertRelevance(int $keywordId, int $entityId, string $type, float $confidence, string $source): bool
    {
        $existing = SjKeywordEntityRelevance::where('keyword_id', $keywordId)
            ->where('entity_id', $entityId)
            ->first();

        if ($existing) {
            // Nur upgraden: direct > brand > local > generic
            $priority = ['direct' => 4, 'brand' => 3, 'local' => 2, 'generic' => 1];
            if (($priority[$type] ?? 0) > ($priority[$existing->attribution_type] ?? 0)) {
                $existing->update([
                    'attribution_type' => $type,
                    'confidence' => $confidence,
                    'source' => $source,
                ]);
            }
            return false;
        }

        SjKeywordEntityRelevance::create([
            'keyword_id' => $keywordId,
            'entity_id' => $entityId,
            'attribution_type' => $type,
            'confidence' => $confidence,
            'source' => $source,
        ]);

        return true;
    }

    // =========================================================================
    // Phase 3: Snapshot als Tages-Aggregat
    // =========================================================================

    protected function upsertSnapshot(
        int $teamId,
        SjEntityUrl $entityUrl,
        string $today,
        array $assignment,
        string $domain,
        int $domainTotalKeywords,
    ): void {
        SjUrlSnapshot::updateOrCreate(
            ['entity_url_id' => $entityUrl->id, 'captured_at' => $today],
            [
                'team_id' => $teamId,
                'keywords_count' => $assignment['keywords_count'],
                'organic_traffic_estimate' => $assignment['traffic'] ?: null,
                'organic_value_cents' => $assignment['value_cents'] ?: null,
                'keywords' => null, // Deprecated: Keywords sind jetzt in sj_keywords + sj_keyword_rankings
                'raw_response' => [
                    'source' => 'ranked_keywords',
                    'scope' => 'url',
                    'domain' => $domain,
                    'domain_total_keywords' => $domainTotalKeywords,
                    'url_matched_keywords' => $assignment['keywords_count'],
                    'brand_keywords' => $assignment['brand_keywords_count'],
                ],
            ]
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function extractDomain(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return null;
        }
        return preg_replace('/^www\./', '', strtolower($host));
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

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'url_snapshots', 'fetch', 'dataforseo', 'keywords'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
            'side_effects' => ['external_api', 'costs'],
        ];
    }
}
