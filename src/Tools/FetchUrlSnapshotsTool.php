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
        return 'POST /syltjunkie/url_snapshots/fetch - Ruft Keyword-Rankings für Entity-URLs via DataForSEO ab. '
            . 'Pro Domain nur 1 API-Call (~$0.10), Keywords werden anhand der rankenden URL der konkreten Entity-URL zugeordnet. '
            . 'GOSCH Alte Bootshalle bekommt nur Keywords wo /standorte/alte-bootshalle/ rankt — nicht die ganze Domain.';
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
                    'description' => 'Optional: Max Keywords pro Domain (höher = mehr Treffer pro Unterseite). Default: 100. Max: 500.',
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
                        'entity_url_count' => count($domainUrls),
                    ];
                }

                return ToolResult::success([
                    'dry_run' => true,
                    'unique_domains' => count($byDomain),
                    'total_urls' => $urls->count(),
                    'domains' => $domainSummary,
                    'estimated_cost_cents' => count($byDomain) * 10,
                    'strategy' => 'Keywords werden pro Domain gefetcht, dann anhand der rankenden URL der spezifischen Entity-URL zugeordnet.',
                ]);
            }

            $api = app(DataForSeoApiService::class);
            $results = [];
            $snapshotsCreated = 0;
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

                    // Keywords der spezifischen Entity-URLs zuordnen
                    $urlAssignments = $this->assignKeywordsToUrls($rankedResults, $domainUrls);

                    // Snapshot pro Entity-URL erstellen
                    foreach ($domainUrls as $entityUrl) {
                        $urlId = $entityUrl->id;
                        $assignment = $urlAssignments[$urlId];

                        $urlResult = [
                            'entity_url_id' => $urlId,
                            'url' => $entityUrl->url,
                            'domain' => $domain,
                            'entity_name' => $entityUrl->entity?->name,
                        ];

                        // Bereits vollständiger Snapshot für heute?
                        $existing = SjUrlSnapshot::where('entity_url_id', $urlId)
                            ->where('captured_at', $today)
                            ->whereNotNull('organic_traffic_estimate')
                            ->first();

                        if ($existing) {
                            $urlResult['skipped'] = 'Snapshot für heute existiert bereits.';
                            $urlResult['snapshot_id'] = $existing->id;
                            $results[] = $urlResult;
                            continue;
                        }

                        // Bestehenden Discovery-Snapshot anreichern oder neuen erstellen
                        $snapshot = SjUrlSnapshot::where('entity_url_id', $urlId)
                            ->where('captured_at', $today)
                            ->first();

                        $snapshotKeywords = $assignment['keywords'];
                        $traffic = $assignment['traffic'];

                        if ($snapshot) {
                            $existingKeywords = $snapshot->keywords ?? [];
                            $mergedKeywords = $this->mergeKeywords($existingKeywords, $snapshotKeywords);

                            $snapshot->update([
                                'keywords' => $mergedKeywords,
                                'organic_traffic_estimate' => $traffic ?: null,
                                'raw_response' => array_merge($snapshot->raw_response ?? [], [
                                    'scope' => 'url',
                                    'domain' => $domain,
                                    'matched_path' => $assignment['matched_path'],
                                    'domain_total_keywords' => count($rankedResults),
                                    'url_matched_keywords' => count($snapshotKeywords),
                                    'unmatched_keywords_count' => $assignment['unmatched_count'],
                                ]),
                            ]);
                        } else {
                            $snapshot = SjUrlSnapshot::create([
                                'team_id' => $rootTeamId,
                                'entity_url_id' => $urlId,
                                'captured_at' => $today,
                                'keywords' => $snapshotKeywords,
                                'organic_traffic_estimate' => $traffic ?: null,
                                'raw_response' => [
                                    'source' => 'ranked_keywords',
                                    'scope' => 'url',
                                    'domain' => $domain,
                                    'matched_path' => $assignment['matched_path'],
                                    'domain_total_keywords' => count($rankedResults),
                                    'url_matched_keywords' => count($snapshotKeywords),
                                    'unmatched_keywords_count' => $assignment['unmatched_count'],
                                ],
                            ]);
                        }

                        $entityUrl->update(['last_checked_at' => now()]);

                        $urlResult['snapshot_id'] = $snapshot->id;
                        $urlResult['keywords_count'] = count($snapshotKeywords);
                        $urlResult['organic_traffic_estimate'] = $traffic;
                        $urlResult['domain_total_keywords'] = count($rankedResults);
                        $urlResult['url_match_rate'] = count($rankedResults) > 0
                            ? round(count($snapshotKeywords) / count($rankedResults) * 100, 1) . '%'
                            : '0%';
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
                'urls' => $results,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    /**
     * Ordnet Keywords den konkreten Entity-URLs zu basierend auf der rankenden URL.
     *
     * Jedes RankedKeywordResult hat ein `url`-Feld das angibt, welche Seite
     * der Domain für dieses Keyword rankt. Wir matchen per URL-Pfad-Prefix:
     * - Entity-URL: gosch.de/standorte/alte-bootshalle/
     * - Keyword rankt auf: gosch.de/standorte/alte-bootshalle/ → Match ✓
     * - Keyword rankt auf: gosch.de/standorte/list/ → kein Match ✗
     *
     * Keywords die keiner Entity-URL zugeordnet werden können (z.B. Homepage-Keywords)
     * werden nicht doppelt gezählt.
     *
     * @param \Platform\Integrations\DTOs\DataForSeo\RankedKeywordResult[] $rankedResults
     * @param SjEntityUrl[] $entityUrls
     * @return array<int, array{keywords: array, traffic: int, matched_path: string, unmatched_count: int}>
     */
    protected function assignKeywordsToUrls(array $rankedResults, array $entityUrls): array
    {
        // Entity-URL Pfade vorbereiten (normalisiert, ohne trailing slash für Prefix-Match)
        $urlPaths = [];
        foreach ($entityUrls as $entityUrl) {
            $path = parse_url($entityUrl->url, PHP_URL_PATH) ?: '/';
            $urlPaths[$entityUrl->id] = [
                'path' => rtrim(strtolower($path), '/'),
                'entity_url' => $entityUrl,
            ];
        }

        // Ergebnis-Container pro Entity-URL
        $assignments = [];
        foreach ($entityUrls as $entityUrl) {
            $assignments[$entityUrl->id] = [
                'keywords' => [],
                'traffic' => 0,
                'matched_path' => $urlPaths[$entityUrl->id]['path'],
                'unmatched_count' => 0,
            ];
        }

        $totalUnmatched = 0;

        foreach ($rankedResults as $rk) {
            $rankedUrl = $rk->url ?? '';
            $rankedPath = rtrim(strtolower(parse_url($rankedUrl, PHP_URL_PATH) ?: '/'), '/');

            // Finde die passende Entity-URL (längster Pfad-Match gewinnt)
            $bestMatch = null;
            $bestMatchLength = -1;

            foreach ($urlPaths as $entityUrlId => $info) {
                $entityPath = $info['path'];

                // Exakter Match oder Prefix-Match (Entity-URL ist Prefix der rankenden URL)
                if ($rankedPath === $entityPath || str_starts_with($rankedPath, $entityPath . '/')) {
                    $pathLength = strlen($entityPath);
                    if ($pathLength > $bestMatchLength) {
                        $bestMatch = $entityUrlId;
                        $bestMatchLength = $pathLength;
                    }
                }
            }

            $keyword = [
                'keyword' => $rk->keyword,
                'position' => $rk->position,
                'search_volume' => $rk->searchVolume,
                'cpc' => $rk->cpc,
                'competition' => $rk->competition,
                'ranked_url' => $rankedUrl,
            ];

            if ($bestMatch !== null) {
                $assignments[$bestMatch]['keywords'][] = $keyword;

                if ($rk->searchVolume && $rk->position) {
                    $ctr = $this->estimateCtr($rk->position);
                    $assignments[$bestMatch]['traffic'] += (int) round($rk->searchVolume * $ctr);
                }
            } else {
                $totalUnmatched++;
            }
        }

        // Unmatched-Count auf alle verteilen (für Transparenz)
        foreach ($assignments as &$a) {
            $a['unmatched_count'] = $totalUnmatched;
        }

        return $assignments;
    }

    /**
     * Extrahiert und normalisiert die Domain aus einer URL.
     */
    protected function extractDomain(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return null;
        }
        return preg_replace('/^www\./', '', strtolower($host));
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
     * Merged SERP-Discovery-Keywords mit Ranked-Keywords.
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
