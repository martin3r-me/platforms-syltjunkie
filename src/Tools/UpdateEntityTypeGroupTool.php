<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjEntityTypeGroup;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class UpdateEntityTypeGroupTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.entity_type_groups.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /syltjunkie/entity-type-groups/{id} - Aktualisiert eine Entity-Type-Gruppe. ERFORDERLICH: group_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'group_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Gruppe.',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID.',
                ],
                'name' => ['type' => 'string', 'description' => 'Optional: Neuer Name.'],
                'code' => ['type' => 'string', 'description' => 'Optional: Neuer Code.'],
                'description' => ['type' => 'string', 'description' => 'Optional: Neue Beschreibung.'],
                'icon' => ['type' => 'string', 'description' => 'Optional: Neues Icon.'],
                'sort_order' => ['type' => 'integer', 'description' => 'Optional: Neue Sortierung.'],
                'show_on_map' => ['type' => 'boolean', 'description' => 'Optional: Auf Karte anzeigen.'],
                'is_active' => ['type' => 'boolean', 'description' => 'Optional: Aktiv/Inaktiv.'],
            ],
            'required' => ['group_id'],
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

            $group = SjEntityTypeGroup::where('team_id', $rootTeamId)->find($arguments['group_id'] ?? 0);
            if (!$group) {
                return ToolResult::error('NOT_FOUND', 'Gruppe nicht gefunden.');
            }

            $updatable = ['name', 'code', 'description', 'icon', 'sort_order', 'show_on_map', 'is_active'];
            foreach ($updatable as $field) {
                if (array_key_exists($field, $arguments)) {
                    $group->{$field} = $arguments[$field];
                }
            }

            $group->save();

            return ToolResult::success([
                'id' => $group->id,
                'code' => $group->code,
                'name' => $group->name,
                'icon' => $group->icon,
                'sort_order' => $group->sort_order,
                'message' => 'Gruppe aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'entity_type_groups', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
