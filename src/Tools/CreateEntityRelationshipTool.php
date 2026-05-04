<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityRelationship;
use Platform\Syltjunkie\Models\SjRelationType;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class CreateEntityRelationshipTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.entity_relationships.POST';
    }

    public function getDescription(): string
    {
        return 'POST /syltjunkie/entity-relationships - Erstellt eine Beziehung zwischen zwei Entities. ERFORDERLICH: source_entity_id, target_entity_id, relation_type_id. Nutze syltjunkie.relation_types.GET für verfügbare Typen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'source_entity_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Quell-Entity ID.'],
                'target_entity_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Ziel-Entity ID.'],
                'relation_type_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Beziehungstyp ID. Nutze syltjunkie.relation_types.GET.'],
                'description' => ['type' => 'string', 'description' => 'Optional: Beschreibung der Beziehung.'],
                'metadata' => ['type' => 'object', 'description' => 'Optional: Zusätzliche Metadaten als JSON.'],
            ],
            'required' => ['source_entity_id', 'target_entity_id', 'relation_type_id'],
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

            $sourceId = $arguments['source_entity_id'] ?? null;
            $targetId = $arguments['target_entity_id'] ?? null;
            $typeId = $arguments['relation_type_id'] ?? null;

            if (!$sourceId || !$targetId || !$typeId) {
                return ToolResult::error('VALIDATION_ERROR', 'source_entity_id, target_entity_id und relation_type_id sind erforderlich.');
            }

            $source = SjEntity::where('team_id', $rootTeamId)->find($sourceId);
            $target = SjEntity::where('team_id', $rootTeamId)->find($targetId);
            $type = SjRelationType::where('team_id', $rootTeamId)->find($typeId);

            if (!$source) return ToolResult::error('NOT_FOUND', 'Quell-Entity nicht gefunden.');
            if (!$target) return ToolResult::error('NOT_FOUND', 'Ziel-Entity nicht gefunden.');
            if (!$type) return ToolResult::error('NOT_FOUND', 'Beziehungstyp nicht gefunden.');

            $rel = SjEntityRelationship::create([
                'team_id' => $rootTeamId,
                'source_entity_id' => $sourceId,
                'target_entity_id' => $targetId,
                'relation_type_id' => $typeId,
                'description' => ($arguments['description'] ?? null) ?: null,
                'metadata' => (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null,
                'is_active' => true,
            ]);

            return ToolResult::success([
                'id' => $rel->id,
                'source' => $source->name,
                'relation' => $type->name,
                'target' => $target->name,
                'message' => "Beziehung erstellt: {$source->name} → {$type->name} → {$target->name}",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'relationships', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
