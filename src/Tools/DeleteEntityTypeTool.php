<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjEntityType;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class DeleteEntityTypeTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.entity_types.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /syltjunkie/entity-types/{id} - Löscht einen Entity-Type. ERFORDERLICH: type_id, confirm=true. Type darf keine Entities mehr enthalten.';
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
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'ERFORDERLICH: Muss true sein um Löschung zu bestätigen.',
                ],
            ],
            'required' => ['type_id', 'confirm'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (empty($arguments['confirm'])) {
                return ToolResult::error('VALIDATION_ERROR', 'confirm=true ist erforderlich.');
            }

            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $type = SjEntityType::where('team_id', $rootTeamId)->find($arguments['type_id'] ?? 0);
            if (!$type) {
                return ToolResult::error('NOT_FOUND', 'Entity-Type nicht gefunden.');
            }

            $entityCount = $type->entities()->count();
            if ($entityCount > 0) {
                return ToolResult::error('HAS_ENTITIES', "Entity-Type enthält noch {$entityCount} Entities. Bitte zuerst die Entities verschieben oder löschen.");
            }

            $name = $type->name;
            $id = $type->id;
            $type->delete();

            return ToolResult::success([
                'id' => $id,
                'name' => $name,
                'message' => "Entity-Type '{$name}' (ID: {$id}) gelöscht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'entity_types', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'destructive',
            'idempotent' => false,
        ];
    }
}
