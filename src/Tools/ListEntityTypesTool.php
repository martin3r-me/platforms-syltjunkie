<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Syltjunkie\Models\SjEntityType;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class ListEntityTypesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.entity_types.GET';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/entity-types - Listet Entity-Types (restaurant, hotel, strand, ...). Filter nach group_id möglich. Liefert extra_field_schema pro Type.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'group_id', 'is_active']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                    ],
                    'group_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Entity-Type-Group ID.',
                    ],
                    'include_schema' => [
                        'type' => 'boolean',
                        'description' => 'Optional: extra_field_schema pro Type mitliefern. Default: false.',
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

            $q = SjEntityType::query()->where('team_id', $rootTeamId)->where('is_active', true);

            if (!empty($arguments['group_id'])) {
                $q->where('group_id', (int) $arguments['group_id']);
            }

            $q->with('group')->withCount('entities');
            $this->applyStandardSearch($q, $arguments, ['name', 'code', 'description']);
            $this->applyStandardSort($q, $arguments, ['name', 'sort_order', 'created_at'], 'sort_order', 'asc');
            $result = $this->applyStandardPaginationResult($q, $arguments);

            $includeSchema = !empty($arguments['include_schema']);

            $items = $result['data']->map(function ($t) use ($includeSchema) {
                $item = [
                    'id' => $t->id,
                    'code' => $t->code,
                    'name' => $t->name,
                    'description' => $t->description,
                    'icon' => $t->icon,
                    'group_id' => $t->group_id,
                    'group_name' => $t->group?->name,
                    'entities_count' => $t->entities_count,
                ];
                if ($includeSchema) {
                    $item['extra_field_schema'] = $t->extra_field_schema;
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
            'tags' => ['syltjunkie', 'entity_types', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
