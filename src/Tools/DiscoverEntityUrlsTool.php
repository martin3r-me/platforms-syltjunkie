<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityUrl;
use Platform\Syltjunkie\Models\SjKeyword;
use Platform\Syltjunkie\Models\SjKeywordEntityRelevance;
use Platform\Syltjunkie\Models\SjKeywordRanking;
use Platform\Syltjunkie\Models\SjUrlSnapshot;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class DiscoverEntityUrlsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    protected const PLATFORM_MAP = [
        'google.com/maps'      => 'google_maps',
        'google.de/maps'       => 'google_maps',
        'maps.google'          => 'google_maps',
        'tripadvisor'          => 'tripadvisor',
        'instagram.com'        => 'instagram',
        'facebook.com'         => 'facebook',
        'booking.com'          => 'booking',
        'yelp'                 => 'yelp',
    ];

    public function getName(): string
    {
        return 'syltjunkie.entity_urls.DISCOVER';
    }

    public function getDescription(): string
    {
        return 'POST /syltjunkie/entity_urls/discover - Entdeckt URLs für eine Entity via Google SERP (DataForSEO). '
            . 'Sucht nach "{name} {ort} Sylt", legt Entity-URLs an und speichert SERP-Keywords normalisiert in sj_keywords + sj_keyword_rankings. '
            . 'Kostet ~$0.10 pro Entity (1 SERP-Call).';
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
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Einzelne Entity-ID. Wenn leer: alle aktiven Entities ohne URLs.',
                ],
                'max_entities' => [
                    'type' => 'integer',
                    'description' => 'Optional: Maximale Anzahl Entities pro Aufruf. Default: 10. Max: 50.',
                    'default' => 10,
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

            $maxEntities = min((int) ($arguments['max_entities'] ?? 10), 50);
            $dryRun = (bool) ($arguments['dry_run'] ?? false);

            if (!empty($arguments['entity_id'])) {
                $entities = SjEntity::where('team_id', $rootTeamId)
                    ->where('is_active', true)
                    ->where('id', (int) $arguments['entity_id'])
                    ->get();
            } else {
                $entities = SjEntity::where('team_id', $rootTeamId)
                    ->where('is_active', true)
                    ->whereDoesntHave('entityUrls', fn($q) => $q->where('is_active', true))
                    ->limit($maxEntities)
                    ->get();
            }

            if ($entities->isEmpty()) {
                return ToolResult::success([
                    'message' => 'Keine Entities zum Verarbeiten gefunden.',
                    'processed' => 0,
                    'urls_created' => 0,
                ]);
            }

            $api = app(DataForSeoApiService::class);
            $totalUrlsCreated = 0;
            $results = [];

            foreach ($entities as $entity) {
                $searchQuery = $this->buildSearchQuery($entity);
                $entityResult = [
                    'entity_id' => $entity->id,
                    'entity_name' => $entity->name,
                    'search_query' => $searchQuery,
                    'urls_found' => [],
                    'urls_created' => 0,
                ];

                try {
                    $serpResults = $api->getSerpOrganic($context->user, $searchQuery);
                    $discoveredUrls = $this->extractUrls($serpResults, $entity);
                    $entityResult['urls_found'] = $discoveredUrls;

                    if (!$dryRun) {
                        $created = $this->createEntityUrls($rootTeamId, $entity, $discoveredUrls, $searchQuery, $serpResults);
                        $entityResult['urls_created'] = $created;
                        $totalUrlsCreated += $created;
                    }
                } catch (\Throwable $e) {
                    $entityResult['error'] = $e->getMessage();
                }

                $results[] = $entityResult;
            }

            return ToolResult::success([
                'processed' => count($results),
                'urls_created' => $totalUrlsCreated,
                'dry_run' => $dryRun,
                'estimated_cost_cents' => count($results) * 10,
                'entities' => $results,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    protected function buildSearchQuery(SjEntity $entity): string
    {
        $parts = [$entity->name];
        if ($entity->ort) {
            $parts[] = $entity->ort;
        }
        $parts[] = 'Sylt';
        return implode(' ', $parts);
    }

    /**
     * @param \Platform\Integrations\DTOs\DataForSeo\SerpOrganicResult[] $serpResults
     */
    protected function extractUrls(array $serpResults, SjEntity $entity): array
    {
        $found = [];
        $websiteUrls = [];

        foreach ($serpResults as $result) {
            if (!$result->url) {
                continue;
            }

            $platform = $this->detectPlatform($result->url, $result->domain);
            $position = $result->position ?? 999;

            if ($platform !== 'website') {
                if (!isset($found[$platform]) || $position < $found[$platform]['position']) {
                    $found[$platform] = [
                        'url' => $result->url,
                        'platform' => $platform,
                        'position' => $position,
                        'title' => $result->title,
                        'domain' => $result->domain,
                    ];
                }
            } else {
                $websiteUrls[] = [
                    'url' => $result->url,
                    'platform' => 'website',
                    'position' => $position,
                    'title' => $result->title,
                    'domain' => $result->domain,
                ];
            }
        }

        if (!empty($websiteUrls)) {
            usort($websiteUrls, fn($a, $b) => $a['position'] <=> $b['position']);
            $found['website'] = $websiteUrls[0];
        }

        return array_values($found);
    }

    protected function detectPlatform(?string $url, ?string $domain): string
    {
        $urlLower = strtolower($url ?? '');
        $domainLower = strtolower($domain ?? '');

        foreach (self::PLATFORM_MAP as $pattern => $platform) {
            if (str_contains($urlLower, $pattern) || str_contains($domainLower, $pattern)) {
                return $platform;
            }
        }

        return 'website';
    }

    /**
     * @param \Platform\Integrations\DTOs\DataForSeo\SerpOrganicResult[] $serpResults
     */
    protected function createEntityUrls(int $teamId, SjEntity $entity, array $discoveredUrls, string $searchQuery, array $serpResults): int
    {
        $created = 0;
        $hasPrimary = SjEntityUrl::where('team_id', $teamId)
            ->where('entity_id', $entity->id)
            ->where('is_primary', true)
            ->where('is_active', true)
            ->exists();

        $rawSerpData = array_map(fn($r) => $r->toArray(), $serpResults);
        $today = now()->toDateString();

        // Search-Query als Keyword normalisiert speichern
        $searchKeyword = SjKeyword::firstOrCreate(
            ['team_id' => $teamId, 'keyword' => strtolower(trim($searchQuery))],
            ['last_fetched_at' => now()]
        );

        // Direct relevance: Entity wird direkt durch SERP-Suche verknüpft
        SjKeywordEntityRelevance::firstOrCreate(
            ['keyword_id' => $searchKeyword->id, 'entity_id' => $entity->id],
            ['attribution_type' => 'direct', 'confidence' => 1.0, 'source' => 'auto_serp']
        );

        foreach ($discoveredUrls as $urlData) {
            $existing = SjEntityUrl::where('team_id', $teamId)
                ->where('entity_id', $entity->id)
                ->where('url', $urlData['url'])
                ->first();

            if ($existing) {
                $this->storeDiscoveryData($teamId, $existing, $searchKeyword, $urlData, $today, $rawSerpData);
                continue;
            }

            $isPrimary = false;
            if (!$hasPrimary && $urlData['platform'] === 'website') {
                $isPrimary = true;
                $hasPrimary = true;
            }

            $entityUrl = SjEntityUrl::create([
                'team_id' => $teamId,
                'entity_id' => $entity->id,
                'url' => $urlData['url'],
                'platform' => $urlData['platform'],
                'is_primary' => $isPrimary,
                'is_active' => true,
                'last_checked_at' => now(),
            ]);

            $this->storeDiscoveryData($teamId, $entityUrl, $searchKeyword, $urlData, $today, $rawSerpData);
            $created++;
        }

        return $created;
    }

    /**
     * Speichert SERP-Discovery-Daten normalisiert: Ranking + Snapshot.
     */
    protected function storeDiscoveryData(
        int $teamId,
        SjEntityUrl $entityUrl,
        SjKeyword $searchKeyword,
        array $urlData,
        string $today,
        array $rawSerpData,
    ): void {
        // Keyword-Ranking für die SERP-Position
        if (isset($urlData['position']) && $urlData['position'] < 999) {
            SjKeywordRanking::firstOrCreate(
                [
                    'keyword_id' => $searchKeyword->id,
                    'entity_url_id' => $entityUrl->id,
                    'captured_at' => $today,
                ],
                [
                    'position' => $urlData['position'],
                    'ranked_url' => $urlData['url'],
                ]
            );
        }

        // URL-Snapshot als Tages-Aggregat
        SjUrlSnapshot::firstOrCreate(
            ['entity_url_id' => $entityUrl->id, 'captured_at' => $today],
            [
                'team_id' => $teamId,
                'keywords_count' => 1,
                'raw_response' => [
                    'source' => 'serp_discovery',
                    'search_query' => $searchKeyword->keyword,
                    'serp_results' => $rawSerpData,
                ],
            ]
        );
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'entity_urls', 'discover', 'dataforseo', 'serp'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
            'side_effects' => ['external_api', 'costs'],
        ];
    }
}
