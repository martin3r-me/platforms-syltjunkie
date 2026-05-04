<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Syltjunkie\Models\SjRelationType;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class ListRelationTypesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.relation_types.GET';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/relation-types - Listet Beziehungstypen (lokalisiert_in, gehört_zu, gelistet_auf, ...). Nutze diese IDs für syltjunkie.entity_relationships.POST.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id']),
            [
                'properties' => [
                    'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
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

            $q = SjRelationType::query()->where('team_id', $rootTeamId)->where('is_active', true);
            $this->applyStandardSearch($q, $arguments, ['name', 'code', 'description']);
            $this->applyStandardSort($q, $arguments, ['name', 'sort_order'], 'sort_order', 'asc');
            $result = $this->applyStandardPaginationResult($q, $arguments);

            $items = $result['data']->map(fn($t) => [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->name,
                'inverse_name' => $t->inverse_name,
                'description' => $t->description,
                'is_directional' => $t->is_directional,
                'is_hierarchical' => $t->is_hierarchical,
            ])->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['syltjunkie', 'relation_types', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
