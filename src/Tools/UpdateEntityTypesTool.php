<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class UpdateEntityTypesTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.entities.types.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /syltjunkie/entities/{id}/types - Typen einer Entity verwalten (setzen, hinzufügen, Primärtyp ändern). Nutzt die Many-to-Many Pivot-Tabelle. ERFORDERLICH: entity_id, entity_type_ids.';
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
                'entity_type_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'ERFORDERLICH: Array der Entity-Type IDs. Reihenfolge bestimmt die Sortierung. Ersetzt alle bisherigen Typ-Zuweisungen.',
                ],
                'primary_type_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID des Primärtyps. Muss in entity_type_ids enthalten sein. Default: erster Eintrag.',
                ],
            ],
            'required' => ['entity_id', 'entity_type_ids'],
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

            $entity = SjEntity::where('team_id', $rootTeamId)->find($arguments['entity_id'] ?? 0);
            if (!$entity) {
                return ToolResult::error('NOT_FOUND', 'Entity nicht gefunden.');
            }

            $typeIds = $arguments['entity_type_ids'] ?? [];
            if (empty($typeIds) || !is_array($typeIds)) {
                return ToolResult::error('VALIDATION_ERROR', 'entity_type_ids muss ein nicht-leeres Array sein.');
            }

            $typeIds = array_map('intval', $typeIds);
            $primaryTypeId = !empty($arguments['primary_type_id']) ? (int) $arguments['primary_type_id'] : null;

            if ($primaryTypeId && !in_array($primaryTypeId, $typeIds)) {
                return ToolResult::error('VALIDATION_ERROR', 'primary_type_id muss in entity_type_ids enthalten sein.');
            }

            $entity->syncEntityTypes($typeIds, $primaryTypeId);

            $entity->load('entityTypes');

            return ToolResult::success([
                'entity_id' => $entity->id,
                'entity_name' => $entity->name,
                'entity_type_id' => $entity->entity_type_id,
                'entity_types' => $entity->entityTypes->map(fn ($t) => [
                    'id' => $t->id,
                    'code' => $t->code,
                    'name' => $t->name,
                    'is_primary' => (bool) $t->pivot->is_primary,
                ])->values()->toArray(),
                'message' => 'Entity-Typen aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'entities', 'types', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
