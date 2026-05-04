<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class GetEntityTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.entity.GET';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/entities/{id} - Ruft eine einzelne Entity ab inkl. Type, Gruppe, extra_fields und Beziehungen. ERFORDERLICH: entity_id.';
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
                'include_relationships' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Beziehungen (ausgehend + eingehend) mitladen. Default: true.',
                    'default' => true,
                ],
            ],
            'required' => ['entity_id'],
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

            $entity = SjEntity::where('team_id', $rootTeamId)
                ->with('entityType.group')
                ->find($arguments['entity_id'] ?? 0);

            if (!$entity) {
                return ToolResult::error('NOT_FOUND', 'Entity nicht gefunden.');
            }

            $data = [
                'id' => $entity->id,
                'uuid' => $entity->uuid,
                'name' => $entity->name,
                'slug' => $entity->slug,
                'description' => $entity->description,
                'ort' => $entity->ort,
                'latitude' => $entity->latitude,
                'longitude' => $entity->longitude,
                'season' => $entity->season,
                'status' => $entity->status,
                'source' => $entity->source,
                'extra_fields' => $entity->extra_fields,
                'is_active' => $entity->is_active,
                'entity_type_id' => $entity->entity_type_id,
                'entity_type_name' => $entity->entityType?->name,
                'entity_type_code' => $entity->entityType?->code,
                'group_name' => $entity->entityType?->group?->name,
                'created_at' => $entity->created_at?->toIso8601String(),
                'updated_at' => $entity->updated_at?->toIso8601String(),
            ];

            $includeRels = $arguments['include_relationships'] ?? true;
            if ($includeRels) {
                $entity->load([
                    'outgoingRelationships.targetEntity',
                    'outgoingRelationships.relationType',
                    'incomingRelationships.sourceEntity',
                    'incomingRelationships.relationType',
                ]);

                $data['outgoing_relationships'] = $entity->outgoingRelationships->map(fn($r) => [
                    'id' => $r->id,
                    'relation_type' => $r->relationType?->name,
                    'relation_code' => $r->relationType?->code,
                    'target_entity_id' => $r->target_entity_id,
                    'target_entity_name' => $r->targetEntity?->name,
                ])->toArray();

                $data['incoming_relationships'] = $entity->incomingRelationships->map(fn($r) => [
                    'id' => $r->id,
                    'relation_type' => $r->relationType?->inverse_name ?? $r->relationType?->name,
                    'relation_code' => $r->relationType?->code,
                    'source_entity_id' => $r->source_entity_id,
                    'source_entity_name' => $r->sourceEntity?->name,
                ])->toArray();
            }

            return ToolResult::success($data);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['syltjunkie', 'entities', 'detail'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
