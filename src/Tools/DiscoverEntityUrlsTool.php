<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityUrl;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class DiscoverEntityUrlsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    /**
     * Domain-Patterns → Platform Mapping.
     * Reihenfolge: spezifischere Patterns zuerst.
     */
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
        return 'POST /syltjunkie/entity_urls/discover - Entdeckt URLs für eine Entity via Google SERP (DataForSEO). Sucht nach "{name} {ort} Sylt" und legt automatisch Entity-URLs an. Kostet ~$0.10 pro Entity (1 SERP-Call). Optional: alle Entities ohne URLs verarbeiten.';
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
                    'description' => 'Optional: Maximale Anzahl Entities pro Aufruf (Schutz vor hohen Kosten). Default: 10. Max: 50.',
                    'default' => 10,
                ],
                'dry_run' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Nur anzeigen was passieren würde, ohne URLs anzulegen. Default: false.',
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

            // Entities ermitteln
            if (!empty($arguments['entity_id'])) {
                $entities = SjEntity::where('team_id', $rootTeamId)
                    ->where('is_active', true)
                    ->where('id', (int) $arguments['entity_id'])
                    ->get();
            } else {
                // Alle aktiven Entities ohne URLs
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
                        $created = $this->createEntityUrls($rootTeamId, $entity, $discoveredUrls);
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
                'estimated_cost_cents' => count($results) * 10, // ~$0.10 pro SERP-Call
                'entities' => $results,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    /**
     * Baut die Google-Suchanfrage für eine Entity.
     */
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
     * Extrahiert und dedupliziert URLs aus SERP-Ergebnissen.
     * Mappt Domains auf Plattformen und wählt pro Plattform die bestplatzierte URL.
     *
     * @param \Platform\Integrations\DTOs\DataForSeo\SerpOrganicResult[] $serpResults
     * @return array<array{url: string, platform: string, position: int, title: ?string, domain: ?string}>
     */
    protected function extractUrls(array $serpResults, SjEntity $entity): array
    {
        $found = [];        // platform => best result
        $websiteUrls = [];  // Alle website-URLs (nicht social/platform)

        foreach ($serpResults as $result) {
            if (!$result->url) {
                continue;
            }

            $platform = $this->detectPlatform($result->url, $result->domain);
            $position = $result->position ?? 999;

            // Pro Plattform nur die bestplatzierte URL behalten
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

        // Beste Website-URL (höchste Position = niedrigste Zahl) als primary
        if (!empty($websiteUrls)) {
            usort($websiteUrls, fn($a, $b) => $a['position'] <=> $b['position']);
            // Nur die Top-Website-URL nehmen (die eigene Website der Entity)
            $found['website'] = $websiteUrls[0];
        }

        return array_values($found);
    }

    /**
     * Erkennt die Plattform anhand der URL/Domain.
     */
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
     * Erstellt Entity-URLs aus den entdeckten Ergebnissen.
     * Überspringt bereits vorhandene URLs (Duplikat-Schutz via unique constraint).
     */
    protected function createEntityUrls(int $teamId, SjEntity $entity, array $discoveredUrls): int
    {
        $created = 0;
        $hasPrimary = SjEntityUrl::where('team_id', $teamId)
            ->where('entity_id', $entity->id)
            ->where('is_primary', true)
            ->where('is_active', true)
            ->exists();

        foreach ($discoveredUrls as $urlData) {
            // Prüfen ob URL bereits existiert
            $exists = SjEntityUrl::where('team_id', $teamId)
                ->where('entity_id', $entity->id)
                ->where('url', $urlData['url'])
                ->exists();

            if ($exists) {
                continue;
            }

            // Erste Website-URL wird primary, wenn noch keine existiert
            $isPrimary = false;
            if (!$hasPrimary && $urlData['platform'] === 'website') {
                $isPrimary = true;
                $hasPrimary = true;
            }

            SjEntityUrl::create([
                'team_id' => $teamId,
                'entity_id' => $entity->id,
                'url' => $urlData['url'],
                'platform' => $urlData['platform'],
                'is_primary' => $isPrimary,
                'is_active' => true,
                'last_checked_at' => now(),
            ]);

            $created++;
        }

        return $created;
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
