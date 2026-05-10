<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjEntityTypeGroup;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class DeleteEntityTypeGroupTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.entity_type_groups.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /syltjunkie/entity-type-groups/{id} - Löscht eine Entity-Type-Gruppe. ERFORDERLICH: group_id, confirm=true. Gruppe darf keine Entity-Types mehr enthalten.';
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
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'ERFORDERLICH: Muss true sein um Löschung zu bestätigen.',
                ],
            ],
            'required' => ['group_id', 'confirm'],
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

            $group = SjEntityTypeGroup::where('team_id', $rootTeamId)->find($arguments['group_id'] ?? 0);
            if (!$group) {
                return ToolResult::error('NOT_FOUND', 'Gruppe nicht gefunden.');
            }

            $typeCount = $group->entityTypes()->count();
            if ($typeCount > 0) {
                return ToolResult::error('HAS_CHILDREN', "Gruppe enthält noch {$typeCount} Entity-Types. Bitte zuerst die Types verschieben oder löschen.");
            }

            $name = $group->name;
            $id = $group->id;
            $group->delete();

            return ToolResult::success([
                'id' => $id,
                'name' => $name,
                'message' => "Gruppe '{$name}' (ID: {$id}) gelöscht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'entity_type_groups', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'destructive',
            'idempotent' => false,
        ];
    }
}
