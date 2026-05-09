<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class ListEntitiesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.entities.GET';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/entities - Listet Syltjunkie-Entities (Restaurants, Hotels, Strände, ...). Filter nach entity_type_id, ort, status, season. Unterstützt filters/search/sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'entity_type_id', 'status', 'season', 'is_active']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                    ],
                    'entity_type_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Entity-Type ID. Nutze syltjunkie.entity_types.GET.',
                    ],
                    'group_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Entity-Type-Group ID (filtert über entity_type.group_id).',
                    ],
                    'ort' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Ort (z.B. "Westerland", "Kampen", "List"). Filtert über die lokalisiert_in-Beziehung.',
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Status. Werte: aktiv, saisonal_geschlossen, dauerhaft_geschlossen.',
                        'enum' => ['aktiv', 'saisonal_geschlossen', 'dauerhaft_geschlossen'],
                    ],
                    'season' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Saison. Werte: year_round, sommer, winter, event.',
                        'enum' => ['year_round', 'sommer', 'winter', 'event'],
                    ],
                    'include_type' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Entity-Type und Gruppe mitladen. Default: true.',
                        'default' => true,
                    ],
                    'include_extra_fields' => [
                        'type' => 'boolean',
                        'description' => 'Optional: extra_fields mitliefern. Default: false.',
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $q = SjEntity::query()->where('team_id', $rootTeamId)->where('is_active', true);

            if (!empty($arguments['entity_type_id'])) {
                $q->where('entity_type_id', (int) $arguments['entity_type_id']);
            }
            if (!empty($arguments['group_id'])) {
                $q->whereHas('entityType', fn($sub) => $sub->where('group_id', (int) $arguments['group_id']));
            }
            if (!empty($arguments['ort'])) {
                $q->whereHas('outgoingRelationships', fn($sub) => $sub
                    ->where('relation_type_id', 1)
                    ->where('is_active', true)
                    ->whereHas('targetEntity', fn($tq) => $tq->where('name', $arguments['ort']))
                );
            }
            if (!empty($arguments['status'])) {
                $q->where('status', $arguments['status']);
            }
            if (!empty($arguments['season'])) {
                $q->where('season', $arguments['season']);
            }

            $includeType = $arguments['include_type'] ?? true;
            if ($includeType) {
                $q->with('entityType.group');
            }

            $q->with([
                'outgoingRelationships' => fn($sub) => $sub->where('relation_type_id', 1)->where('is_active', true),
                'outgoingRelationships.targetEntity:id,name',
            ]);

            $this->applyStandardFilters($q, $arguments, ['team_id', 'entity_type_id', 'status', 'season', 'is_active', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['name', 'slug', 'description']);
            $this->applyStandardSort($q, $arguments, ['name', 'created_at', 'status'], 'name', 'asc');
            $result = $this->applyStandardPaginationResult($q, $arguments);

            $includeExtra = !empty($arguments['include_extra_fields']);

            $items = $result['data']->map(function ($e) use ($includeType, $includeExtra) {
                $item = [
                    'id' => $e->id,
                    'name' => $e->name,
                    'slug' => $e->slug,
                    'ort' => $e->outgoingRelationships->first()?->targetEntity?->name,
                    'status' => $e->status,
                    'season' => $e->season,
                    'source' => $e->source,
                    'entity_type_id' => $e->entity_type_id,
                ];
                if ($includeType) {
                    $item['entity_type_name'] = $e->entityType?->name;
                    $item['entity_type_code'] = $e->entityType?->code;
                    $item['group_name'] = $e->entityType?->group?->name;
                }
                if ($includeExtra) {
                    $item['extra_fields'] = $e->extra_fields;
                }
                if ($e->latitude && $e->longitude) {
                    $item['latitude'] = $e->latitude;
                    $item['longitude'] = $e->longitude;
                }
                return $item;
            })->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['syltjunkie', 'entities', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
