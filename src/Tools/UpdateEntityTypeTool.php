<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjEntityType;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class UpdateEntityTypeTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.entity_types.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /syltjunkie/entity-types/{id} - Aktualisiert einen Entity-Type. ERFORDERLICH: type_id. Nutze group_id um den Type in eine andere Gruppe zu verschieben.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Entity-Types.',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID.',
                ],
                'name' => ['type' => 'string', 'description' => 'Optional: Neuer Name.'],
                'code' => ['type' => 'string', 'description' => 'Optional: Neuer Code.'],
                'group_id' => ['type' => 'integer', 'description' => 'Optional: Neue Gruppen-ID (verschiebt den Type).'],
                'description' => ['type' => 'string', 'description' => 'Optional: Neue Beschreibung.'],
                'icon' => ['type' => 'string', 'description' => 'Optional: Neues Icon.'],
                'sort_order' => ['type' => 'integer', 'description' => 'Optional: Neue Sortierung.'],
                'extra_field_schema' => ['type' => 'object', 'description' => 'Optional: Neues Extra-Field-Schema.'],
                'is_active' => ['type' => 'boolean', 'description' => 'Optional: Aktiv/Inaktiv.'],
            ],
            'required' => ['type_id'],
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

            $type = SjEntityType::where('team_id', $rootTeamId)->find($arguments['type_id'] ?? 0);
            if (!$type) {
                return ToolResult::error('NOT_FOUND', 'Entity-Type nicht gefunden.');
            }

            $updatable = ['name', 'code', 'group_id', 'description', 'icon', 'sort_order', 'is_active'];
            foreach ($updatable as $field) {
                if (array_key_exists($field, $arguments)) {
                    $type->{$field} = $arguments[$field];
                }
            }
            if (array_key_exists('extra_field_schema', $arguments)) {
                $type->extra_field_schema = is_array($arguments['extra_field_schema']) ? $arguments['extra_field_schema'] : null;
            }

            $type->save();
            $type->load('group');

            return ToolResult::success([
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'group_id' => $type->group_id,
                'group_name' => $type->group?->name,
                'icon' => $type->icon,
                'message' => 'Entity-Type aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'entity_types', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
