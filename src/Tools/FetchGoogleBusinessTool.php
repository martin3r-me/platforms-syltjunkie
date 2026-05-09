<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\DTOs\DataForSeo\GoogleBusinessInfoResult;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityUrl;
use Platform\Syltjunkie\Models\SjUrlSnapshot;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class FetchGoogleBusinessTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.google_business.FETCH';
    }

    public function getDescription(): string
    {
        return 'POST /syltjunkie/google_business/fetch - Ruft Google Business Profile Daten für Entities ab. '
            . 'Pro Entity 1 API-Call (~$0.01-0.02). Liefert Rating, Review-Anzahl, Rating-Verteilung, '
            . 'Claimed-Status, Kategorie, Öffnungszeiten, Fotos, Place Topics.';
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
                    'description' => 'Optional: Nur diese Entity abfragen.',
                ],
                'max_entities' => [
                    'type' => 'integer',
                    'description' => 'Optional: Maximale Anzahl Entities. Default: 10. Max: 50.',
                    'default' => 10,
                ],
                'dry_run' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Nur anzeigen was passieren würde. Default: false.',
                    'default' => false,
                ],
                'search_keyword' => [
                    'type' => 'string',
                    'description' => 'Optional: Eigenes Keyword statt Auto-Generierung. Z.B. "Restaurant Sansibar Sylt".',
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
            $searchKeyword = $arguments['search_keyword'] ?? null;

            // Entities laden
            $q = SjEntity::query()
                ->where('team_id', $rootTeamId)
                ->where('is_active', true)
                ->with([
                    'entityUrls' => fn($q) => $q->where('is_active', true),
                    'entityType:id,name,code',
                ]);

            if (!empty($arguments['entity_id'])) {
                $q->where('id', (int) $arguments['entity_id']);
            }

            $entities = $q->limit($maxEntities)->get();

            if ($entities->isEmpty()) {
                return ToolResult::success([
                    'message' => 'Keine Entities zum Verarbeiten gefunden.',
                    'processed' => 0,
                ]);
            }

            if ($dryRun) {
                return $this->buildDryRunResult($entities);
            }

            $api = app(DataForSeoApiService::class);
            $results = [];
            $apiCallsMade = 0;
            $today = now()->toDateString();
            $locationCode = config('integrations.dataforseo.default_location_code', 2276);

            foreach ($entities as $entity) {
                try {
                    $googleMapsUrl = $entity->entityUrls->firstWhere('platform', 'google_maps');
                    $placeId = $googleMapsUrl?->google_place_id;

                    // Keyword bauen & iterativ durchprobieren
                    $best = null;
                    $keyword = null;

                    if ($placeId) {
                        // Place-ID hat höchste Prio — ein Call reicht
                        $keyword = "place_id:{$placeId}";
                        $businessResults = $api->getGoogleBusinessInfo($context->user, $keyword, $locationCode);
                        $apiCallsMade++;

                        if (!empty($businessResults)) {
                            $best = $this->findBestResult($businessResults, $placeId, $entity);
                        }
                    } else {
                        // Keyword-Varianten durchprobieren
                        $variants = $searchKeyword
                            ? [$searchKeyword]
                            : $this->buildKeywordVariants($entity);

                        foreach ($variants as $variant) {
                            $keyword = $variant;
                            $businessResults = $api->getGoogleBusinessInfo($context->user, $keyword, $locationCode);
                            $apiCallsMade++;

                            if (!empty($businessResults)) {
                                $best = $this->findBestResult($businessResults, null, $entity);
                                if ($best) {
                                    break;
                                }
                            }
                        }
                    }

                    if (!$best) {
                        $results[] = [
                            'entity_id' => $entity->id,
                            'entity_name' => $entity->name,
                            'keyword' => $keyword,
                            'status' => 'no_results',
                        ];
                        continue;
                    }

                    // EntityUrl anlegen/updaten
                    $googleMapsUrl = $this->upsertGoogleMapsUrl(
                        $rootTeamId, $entity, $googleMapsUrl, $best
                    );

                    // Snapshot upserten
                    SjUrlSnapshot::updateOrCreate(
                        [
                            'entity_url_id' => $googleMapsUrl->id,
                            'captured_at' => $today,
                        ],
                        [
                            'team_id' => $rootTeamId,
                            'review_count' => $best->ratingVotesCount,
                            'average_rating' => $best->ratingValue,
                            'raw_response' => $best->toArray(),
                        ]
                    );

                    // Entity extra_fields updaten
                    $extraFields = $entity->extra_fields ?? [];
                    $extraFields['google_is_claimed'] = $best->isClaimed;
                    $extraFields['google_category'] = $best->category;
                    $extraFields['google_current_status'] = $best->currentStatus;
                    $entity->update(['extra_fields' => $extraFields]);

                    // last_checked_at updaten
                    $googleMapsUrl->update(['last_checked_at' => now()]);

                    $results[] = [
                        'entity_id' => $entity->id,
                        'entity_name' => $entity->name,
                        'keyword' => $keyword,
                        'status' => 'ok',
                        'title' => $best->title,
                        'rating' => $best->ratingValue,
                        'reviews' => $best->ratingVotesCount,
                        'is_claimed' => $best->isClaimed,
                        'category' => $best->category,
                        'place_id' => $best->placeId,
                        'entity_url_id' => $googleMapsUrl->id,
                    ];
                } catch (\Throwable $e) {
                    $results[] = [
                        'entity_id' => $entity->id,
                        'entity_name' => $entity->name,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return ToolResult::success([
                'processed' => count($results),
                'api_calls_made' => $apiCallsMade,
                'estimated_cost_cents' => $apiCallsMade * 2,
                'entities' => $results,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    protected function buildDryRunResult($entities): ToolResult
    {
        $planned = [];
        $totalMaxCalls = 0;

        foreach ($entities as $entity) {
            $googleMapsUrl = $entity->entityUrls->firstWhere('platform', 'google_maps');
            $placeId = $googleMapsUrl?->google_place_id;

            if ($placeId) {
                $keywords = ["place_id:{$placeId}"];
            } else {
                $keywords = $this->buildKeywordVariants($entity);
            }

            $totalMaxCalls += count($keywords);

            $planned[] = [
                'entity_id' => $entity->id,
                'entity_name' => $entity->name,
                'keyword_variants' => $keywords,
                'has_google_maps_url' => $googleMapsUrl !== null,
                'has_place_id' => $placeId !== null,
                'has_coordinates' => $entity->latitude && $entity->longitude,
            ];
        }

        return ToolResult::success([
            'dry_run' => true,
            'total_entities' => count($planned),
            'max_api_calls' => $totalMaxCalls,
            'estimated_max_cost_cents' => $totalMaxCalls * 2,
            'entities' => $planned,
        ]);
    }

    /**
     * @param GoogleBusinessInfoResult[] $results
     */
    protected function findBestResult(array $results, ?string $knownPlaceId, ?SjEntity $entity = null): ?GoogleBusinessInfoResult
    {
        // 1. Place-ID Match (höchste Prio)
        if ($knownPlaceId) {
            foreach ($results as $result) {
                if ($result->placeId === $knownPlaceId) {
                    return $result;
                }
            }
        }

        // 2. Geo-Match: nächstes Ergebnis innerhalb 2km
        if ($entity?->latitude && $entity?->longitude) {
            $closest = null;
            $closestDist = PHP_FLOAT_MAX;

            foreach ($results as $r) {
                if (!$r->latitude || !$r->longitude) {
                    continue;
                }
                $dist = $this->haversineKm((float) $entity->latitude, (float) $entity->longitude, $r->latitude, $r->longitude);
                if ($dist < 2.0 && $dist < $closestDist) {
                    $closest = $r;
                    $closestDist = $dist;
                }
            }

            if ($closest) {
                return $closest;
            }

            return null;
        }

        // 3. Fallback: erstes Ergebnis (nur wenn keine Geo-Validierung möglich)
        return $results[0];
    }

    protected function buildKeywordVariants(SjEntity $entity): array
    {
        $name = $entity->name;
        $ortName = $entity->ortEntity()?->name;
        $entityTypeName = $entity->entityType?->name;

        // Nur sinnvolle Entity-Types als Prefix verwenden
        $usefulTypes = ['Restaurant', 'Café', 'Cafe', 'Hotel', 'Bar', 'Bistro', 'Pension', 'Ferienwohnung', 'Imbiss', 'Bäckerei', 'Boutique', 'Galerie', 'Spa', 'Wellness'];
        $useType = $entityTypeName && in_array($entityTypeName, $usefulTypes, true);

        $variants = [];

        // 1. "$name $ort"
        if ($ortName) {
            $variants[] = trim("{$name} {$ortName}");
        }

        // 2. "$entityType $name $ort"
        if ($useType && $ortName) {
            $variants[] = trim("{$entityTypeName} {$name} {$ortName}");
        }

        // 3. "$name Sylt"
        if ($ortName !== 'Sylt') {
            $variants[] = trim("{$name} Sylt");
        }

        // 4. "$entityType $name Sylt"
        if ($useType && $ortName !== 'Sylt') {
            $variants[] = trim("{$entityTypeName} {$name} Sylt");
        }

        // 5. "$name" allein (nur wenn Geo-Koordinaten vorhanden)
        if ($entity->latitude && $entity->longitude) {
            $variants[] = $name;
        }

        // Deduplizieren (Reihenfolge beibehalten)
        return array_values(array_unique($variants));
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    protected function upsertGoogleMapsUrl(
        int $teamId,
        SjEntity $entity,
        ?SjEntityUrl $existingUrl,
        GoogleBusinessInfoResult $result,
    ): SjEntityUrl {
        if ($existingUrl) {
            // Update place_id if we didn't have one
            if (!$existingUrl->google_place_id && $result->placeId) {
                $existingUrl->update(['google_place_id' => $result->placeId]);
            }
            return $existingUrl;
        }

        // Create new google_maps EntityUrl
        $url = $result->cid
            ? "https://www.google.com/maps?cid={$result->cid}"
            : "https://www.google.com/maps/place/?q=place_id:{$result->placeId}";

        return SjEntityUrl::create([
            'team_id' => $teamId,
            'entity_id' => $entity->id,
            'url' => $url,
            'platform' => 'google_maps',
            'is_primary' => false,
            'is_active' => true,
            'google_place_id' => $result->placeId,
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'google_business', 'fetch', 'dataforseo', 'ratings'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
            'side_effects' => ['external_api', 'costs'],
        ];
    }
}
