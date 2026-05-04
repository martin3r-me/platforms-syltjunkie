<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Syltjunkie\Models\SjEntityTypeGroup;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class ListEntityTypeGroupsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.entity_type_groups.GET';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/entity-type-groups - Listet Entity-Type-Gruppen (place, business, infrastructure, ...). Jede Gruppe enthält mehrere Entity-Types.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'is_active']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                    ],
                    'include_types' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Entity-Types pro Gruppe mitladen inkl. Entity-Count. Default: false.',
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

            $q = SjEntityTypeGroup::query()->where('team_id', $rootTeamId)->where('is_active', true);

            $this->applyStandardSearch($q, $arguments, ['name', 'code', 'description']);
            $this->applyStandardSort($q, $arguments, ['name', 'sort_order', 'created_at'], 'sort_order', 'asc');
            $result = $this->applyStandardPaginationResult($q, $arguments);

            $includeTypes = !empty($arguments['include_types']);

            $items = $result['data']->map(function ($g) use ($includeTypes) {
                $item = [
                    'id' => $g->id,
                    'code' => $g->code,
                    'name' => $g->name,
                    'description' => $g->description,
                    'icon' => $g->icon,
                    'sort_order' => $g->sort_order,
                ];

                if ($includeTypes) {
                    $g->load(['entityTypes' => fn($q) => $q->where('is_active', true)->withCount('entities')->orderBy('sort_order')]);
                    $item['entity_types'] = $g->entityTypes->map(fn($t) => [
                        'id' => $t->id,
                        'code' => $t->code,
                        'name' => $t->name,
                        'icon' => $t->icon,
                        'entities_count' => $t->entities_count,
                    ])->toArray();
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
            'tags' => ['syltjunkie', 'entity_type_groups', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
