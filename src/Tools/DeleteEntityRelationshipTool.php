<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjEntityRelationship;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class DeleteEntityRelationshipTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.entity_relationships.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /syltjunkie/entity-relationships/{id} - Löscht eine Beziehung zwischen zwei Entities. ERFORDERLICH: relationship_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'relationship_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID der Beziehung.'],
            ],
            'required' => ['relationship_id'],
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

            $relationshipId = $arguments['relationship_id'] ?? null;

            if (!$relationshipId) {
                return ToolResult::error('VALIDATION_ERROR', 'relationship_id ist erforderlich.');
            }

            $rel = SjEntityRelationship::where('team_id', $rootTeamId)->find($relationshipId);

            if (!$rel) {
                return ToolResult::error('NOT_FOUND', 'Beziehung nicht gefunden.');
            }

            $source = $rel->sourceEntity?->name ?? "Entity #{$rel->source_entity_id}";
            $target = $rel->targetEntity?->name ?? "Entity #{$rel->target_entity_id}";
            $type = $rel->relationType?->name ?? "Typ #{$rel->relation_type_id}";

            $rel->delete();

            return ToolResult::success([
                'id' => $relationshipId,
                'message' => "Beziehung gelöscht: {$source} → {$type} → {$target}",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'relationships', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
