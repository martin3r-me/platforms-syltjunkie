<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Syltjunkie\Models\SjEntityUrl;
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
        return 'POST /syltjunkie/url_snapshots/fetch - Ruft Keyword-Rankings und Traffic für Entity-URLs via DataForSEO ab. Dedupliziert nach Domain: pro Domain nur 1 API-Call (~$0.10), alle Entity-URLs derselben Domain teilen die Daten. Snapshots werden mit scope=domain markiert, damit bei Aggregation nicht doppelt gezählt wird.';
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
                    'description' => 'Optional: Maximale Anzahl URLs pro Aufruf. Default: 10. Max: 50.',
                    'default' => 10,
                ],
                'keywords_limit' => [
                    'type' => 'integer',
                    'description' => 'Optional: Max Keywords pro Domain. Default: 50. Max: 200.',
                    'default' => 50,
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
            $keywordsLimit = min((int) ($arguments['keywords_limit'] ?? 50), 200);
            $dryRun = (bool) ($arguments['dry_run'] ?? false);
            $platformFilter = $arguments['platform'] ?? 'website';

            // URLs ermitteln
            $q = SjEntityUrl::query()
                ->where('team_id', $rootTeamId)
                ->where('is_active', true)
                ->with('entity:id,name,slug');

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
                    'snapshots_created' => 0,
                ]);
            }

            // Nach Domain gruppieren — 1 API-Call pro Domain
            $byDomain = [];
            foreach ($urls as $entityUrl) {
                $domain = parse_url($entityUrl->url, PHP_URL_HOST);
                if (!$domain) {
                    continue;
                }
                // www. normalisieren
                $domain = preg_replace('/^www\./', '', strtolower($domain));
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
                            'entity_name' => $u->entity?->name,
                        ], $domainUrls),
                        'entity_url_count' => count($domainUrls),
                    ];
                }

                return ToolResult::success([
                    'dry_run' => true,
                    'unique_domains' => count($byDomain),
                    'total_urls' => $urls->count(),
                    'domains' => $domainSummary,
                    'estimated_cost_cents' => count($byDomain) * 10,
                    'note' => 'Kosten basieren auf unique Domains, nicht URLs.',
                ]);
            }

            $api = app(DataForSeoApiService::class);
            $results = [];
            $snapshotsCreated = 0;
            $apiCallsMade = 0;
            $today = now()->toDateString();

            foreach ($byDomain as $domain => $domainUrls) {
                // Prüfe ob ALLE URLs dieser Domain heute schon vollständige Snapshots haben
                $urlIds = array_map(fn($u) => $u->id, $domainUrls);
                $existingCount = SjUrlSnapshot::whereIn('entity_url_id', $urlIds)
                    ->where('captured_at', $today)
                    ->whereNotNull('organic_traffic_estimate')
                    ->count();

                if ($existingCount === count($domainUrls)) {
                    foreach ($domainUrls as $entityUrl) {
                        $results[] = [
                            'entity_url_id' => $entityUrl->id,
                            'url' => $entityUrl->url,
                            'domain' => $domain,
                            'entity_name' => $entityUrl->entity?->name,
                            'skipped' => 'Alle URLs dieser Domain haben bereits Snapshots für heute.',
                        ];
                    }
                    continue;
                }

                try {
                    // 1 API-Call pro unique Domain
                    $rankedResults = $api->getRankedKeywords(
                        $context->user,
                        $domain,
                        null,
                        null,
                        $keywordsLimit
                    );
                    $apiCallsMade++;

                    // Keywords aufbereiten
                    $keywords = [];
                    $totalTraffic = 0;
                    foreach ($rankedResults as $rk) {
                        $keywords[] = [
                            'keyword' => $rk->keyword,
                            'position' => $rk->position,
                            'search_volume' => $rk->searchVolume,
                            'cpc' => $rk->cpc,
                            'competition' => $rk->competition,
                        ];

                        if ($rk->searchVolume && $rk->position) {
                            $ctr = $this->estimateCtr($rk->position);
                            $totalTraffic += (int) round($rk->searchVolume * $ctr);
                        }
                    }

                    $rawRankedData = array_map(fn($r) => $r->toArray(), $rankedResults);

                    // Snapshot für JEDE Entity-URL dieser Domain erstellen/updaten
                    foreach ($domainUrls as $entityUrl) {
                        $urlResult = [
                            'entity_url_id' => $entityUrl->id,
                            'url' => $entityUrl->url,
                            'domain' => $domain,
                            'entity_name' => $entityUrl->entity?->name,
                        ];

                        // Bestehenden Snapshot für heute prüfen (z.B. SERP-Discovery)
                        $snapshot = SjUrlSnapshot::where('entity_url_id', $entityUrl->id)
                            ->where('captured_at', $today)
                            ->first();

                        if ($snapshot && $snapshot->organic_traffic_estimate !== null) {
                            $urlResult['skipped'] = 'Snapshot für heute existiert bereits.';
                            $urlResult['snapshot_id'] = $snapshot->id;
                            $results[] = $urlResult;
                            continue;
                        }

                        if ($snapshot) {
                            // Discovery-Snapshot anreichern
                            $existingKeywords = $snapshot->keywords ?? [];
                            $mergedKeywords = $this->mergeKeywords($existingKeywords, $keywords);

                            $snapshot->update([
                                'keywords' => $mergedKeywords,
                                'organic_traffic_estimate' => $totalTraffic ?: null,
                                'raw_response' => array_merge($snapshot->raw_response ?? [], [
                                    'scope' => 'domain',
                                    'domain' => $domain,
                                    'ranked_keywords' => $rawRankedData,
                                ]),
                            ]);
                        } else {
                            $snapshot = SjUrlSnapshot::create([
                                'team_id' => $rootTeamId,
                                'entity_url_id' => $entityUrl->id,
                                'captured_at' => $today,
                                'keywords' => $keywords,
                                'organic_traffic_estimate' => $totalTraffic ?: null,
                                'raw_response' => [
                                    'source' => 'ranked_keywords',
                                    'scope' => 'domain',
                                    'domain' => $domain,
                                    'ranked_keywords' => $rawRankedData,
                                ],
                            ]);
                        }

                        $entityUrl->update(['last_checked_at' => now()]);

                        $urlResult['snapshot_id'] = $snapshot->id;
                        $urlResult['keywords_count'] = count($snapshot->keywords);
                        $urlResult['organic_traffic_estimate'] = $totalTraffic;
                        $urlResult['shared_domain_data'] = count($domainUrls) > 1;
                        $snapshotsCreated++;

                        $results[] = $urlResult;
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
                'snapshots_created' => $snapshotsCreated,
                'api_calls_made' => $apiCallsMade,
                'unique_domains' => count($byDomain),
                'estimated_cost_cents' => $apiCallsMade * 10,
                'deduplication_note' => 'Snapshots mit scope=domain teilen Daten. Bei Aggregation pro Domain nur 1x zählen.',
                'urls' => $results,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    /**
     * Schätzt die Click-Through-Rate basierend auf der SERP-Position.
     */
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

    /**
     * Merged SERP-Discovery-Keywords mit Ranked-Keywords (Discovery-Daten bleiben erhalten).
     */
    protected function mergeKeywords(array $existing, array $ranked): array
    {
        $byKeyword = [];

        foreach ($ranked as $kw) {
            $byKeyword[strtolower($kw['keyword'])] = $kw;
        }

        foreach ($existing as $kw) {
            $key = strtolower($kw['keyword']);
            if (!isset($byKeyword[$key])) {
                $byKeyword[$key] = $kw;
            }
        }

        return array_values($byKeyword);
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
