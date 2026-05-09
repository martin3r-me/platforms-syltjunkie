<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityRelationship;
use Platform\Syltjunkie\Models\SjEntityUrl;
use Platform\Syltjunkie\Models\SjUrlSnapshot;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class DeleteEntityTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.entities.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /syltjunkie/entities/{id} - Löscht eine Syltjunkie-Entity inkl. URLs, Snapshots und Relationships. ERFORDERLICH: entity_id, confirm=true.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Entity.',
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
            'required' => ['entity_id', 'confirm'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (empty($arguments['confirm'])) {
                return ToolResult::error('VALIDATION_ERROR', 'confirm=true ist erforderlich um die Löschung zu bestätigen.');
            }

            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $entity = SjEntity::where('team_id', $rootTeamId)
                ->withTrashed()
                ->find($arguments['entity_id'] ?? 0);

            if (!$entity) {
                return ToolResult::error('NOT_FOUND', 'Entity nicht gefunden.');
            }

            $entityName = $entity->name;
            $entityId = $entity->id;

            // Delete snapshots for all entity URLs
            $urlIds = SjEntityUrl::where('entity_id', $entityId)->pluck('id');
            if ($urlIds->isNotEmpty()) {
                SjUrlSnapshot::whereIn('entity_url_id', $urlIds)->delete();
            }

            // Delete entity URLs
            SjEntityUrl::where('entity_id', $entityId)->delete();

            // Delete relationships (both directions)
            SjEntityRelationship::where('source_entity_id', $entityId)
                ->orWhere('target_entity_id', $entityId)
                ->forceDelete();

            // Force-delete the entity
            $entity->forceDelete();

            return ToolResult::success([
                'id' => $entityId,
                'name' => $entityName,
                'message' => "Entity '{$entityName}' (ID: {$entityId}) und alle zugehörigen Daten gelöscht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'entities', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'destructive',
            'idempotent' => false,
        ];
    }
}
