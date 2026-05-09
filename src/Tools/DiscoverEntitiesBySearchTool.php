<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityUrl;
use Platform\Syltjunkie\Models\SjUrlSnapshot;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class DiscoverEntitiesBySearchTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    private const SYLTER_ORTE = [
        'Westerland', 'Kampen', 'List', 'Rantum', 'Hörnum',
        'Wenningstedt', 'Keitum', 'Tinnum', 'Archsum', 'Morsum',
        'Braderup', 'Munkmarsch',
    ];

    public function getName(): string
    {
        return 'syltjunkie.entities.DISCOVER';
    }

    public function getDescription(): string
    {
        return 'Sucht per Google Business API nach Entities und legt neue Einträge an. '
            . 'Pro Query 1 API-Call (~$0.01-0.02). Dedup per google_place_id und Name.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'queries' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'ERFORDERLICH: Suchbegriffe, z.B. ["Restaurant Sylt", "Hotel Kampen Sylt"].',
                ],
                'entity_type_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: Entity-Type-ID für neue Entities.',
                ],
                'max_results_per_query' => [
                    'type' => 'integer',
                    'description' => 'Max Ergebnisse pro Query. Default 20.',
                ],
                'dry_run' => [
                    'type' => 'boolean',
                    'description' => 'Wenn true, nur anzeigen was passieren würde.',
                ],
            ],
            'required' => ['queries', 'entity_type_id'],
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

            $queries = $arguments['queries'] ?? [];
            $entityTypeId = (int) $arguments['entity_type_id'];
            $maxResults = min((int) ($arguments['max_results_per_query'] ?? 20), 50);
            $dryRun = (bool) ($arguments['dry_run'] ?? false);

            if (empty($queries)) {
                return ToolResult::error('VALIDATION', 'queries darf nicht leer sein.');
            }

            $api = app(DataForSeoApiService::class);
            $locationCode = config('integrations.dataforseo.default_location_code', 2276);
            $today = now()->toDateString();

            $entitiesCreated = 0;
            $entitiesSkipped = 0;
            $apiCallsMade = 0;
            $details = [];

            // Pre-load existing place_ids and names for dedup
            $existingPlaceIds = SjEntityUrl::where('team_id', $rootTeamId)
                ->whereNotNull('google_place_id')
                ->pluck('google_place_id')
                ->flip()
                ->toArray();

            $existingNames = SjEntity::where('team_id', $rootTeamId)
                ->pluck('name', 'id')
                ->map(fn($n) => mb_strtolower($n))
                ->flip()
                ->toArray();

            foreach ($queries as $query) {
                try {
                    $businessResults = $api->getGoogleBusinessInfo(
                        $context->user,
                        $query,
                        $locationCode,
                    );
                    $apiCallsMade++;

                    $resultsSlice = array_slice($businessResults, 0, $maxResults);

                    foreach ($resultsSlice as $result) {
                        // Dedup by place_id
                        if ($result->placeId && isset($existingPlaceIds[$result->placeId])) {
                            $entitiesSkipped++;
                            continue;
                        }

                        // Dedup by name (case-insensitive)
                        $lowerName = mb_strtolower($result->title);
                        if (isset($existingNames[$lowerName])) {
                            $entitiesSkipped++;
                            continue;
                        }

                        if ($dryRun) {
                            $entitiesCreated++;
                            $details[] = [
                                'query' => $query,
                                'title' => $result->title,
                                'place_id' => $result->placeId,
                                'ort' => $this->extractOrt($result->address),
                                'dry_run' => true,
                            ];
                            // Track for dedup within same run
                            if ($result->placeId) {
                                $existingPlaceIds[$result->placeId] = true;
                            }
                            $existingNames[$lowerName] = true;
                            continue;
                        }

                        // Extract Ort from address
                        $ort = $this->extractOrt($result->address);

                        // Create Entity
                        $entity = SjEntity::create([
                            'team_id' => $rootTeamId,
                            'entity_type_id' => $entityTypeId,
                            'name' => $result->title,
                            'source' => 'import_google',
                            'latitude' => $result->latitude,
                            'longitude' => $result->longitude,
                            'ort' => $ort,
                            'status' => 'aktiv',
                            'is_active' => true,
                        ]);

                        // Create Google Maps EntityUrl
                        $googleUrl = $result->cid
                            ? "https://www.google.com/maps?cid={$result->cid}"
                            : "https://www.google.com/maps/place/?q=place_id:{$result->placeId}";

                        $entityUrl = SjEntityUrl::create([
                            'team_id' => $rootTeamId,
                            'entity_id' => $entity->id,
                            'url' => $googleUrl,
                            'platform' => 'google_maps',
                            'is_primary' => false,
                            'is_active' => true,
                            'google_place_id' => $result->placeId,
                            'last_checked_at' => now(),
                        ]);

                        // Create snapshot with rating/reviews
                        if ($result->ratingValue || $result->ratingVotesCount) {
                            SjUrlSnapshot::create([
                                'team_id' => $rootTeamId,
                                'entity_url_id' => $entityUrl->id,
                                'captured_at' => $today,
                                'review_count' => $result->ratingVotesCount,
                                'average_rating' => $result->ratingValue,
                                'raw_response' => $result->toArray(),
                            ]);
                        }

                        // Create website URL if available
                        if ($result->url) {
                            SjEntityUrl::create([
                                'team_id' => $rootTeamId,
                                'entity_id' => $entity->id,
                                'url' => $result->url,
                                'platform' => 'website',
                                'is_primary' => true,
                                'is_active' => true,
                            ]);
                        }

                        $entitiesCreated++;

                        // Track for dedup within same run
                        if ($result->placeId) {
                            $existingPlaceIds[$result->placeId] = true;
                        }
                        $existingNames[$lowerName] = true;

                        $details[] = [
                            'query' => $query,
                            'entity_id' => $entity->id,
                            'title' => $result->title,
                            'place_id' => $result->placeId,
                            'ort' => $ort,
                            'rating' => $result->ratingValue,
                            'reviews' => $result->ratingVotesCount,
                            'has_website' => $result->url !== null,
                        ];
                    }
                } catch (\Throwable $e) {
                    $details[] = [
                        'query' => $query,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return ToolResult::success([
                'queries_executed' => count($queries),
                'entities_created' => $entitiesCreated,
                'entities_skipped' => $entitiesSkipped,
                'api_calls_made' => $apiCallsMade,
                'estimated_cost_cents' => $apiCallsMade * 2,
                'dry_run' => $dryRun,
                'details' => $details,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    protected function extractOrt(?string $address): ?string
    {
        if (!$address) {
            return null;
        }

        foreach (self::SYLTER_ORTE as $ort) {
            if (mb_stripos($address, $ort) !== false) {
                return $ort;
            }
        }

        return null;
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'entities', 'discover', 'google_business', 'dataforseo'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
            'side_effects' => ['external_api', 'costs'],
        ];
    }
}
