<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityRelationship;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class UpdateEntityTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.entities.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /syltjunkie/entities/{id} - Aktualisiert eine Syltjunkie-Entity. ERFORDERLICH: entity_id. Alle anderen Felder optional.';
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
                'name' => ['type' => 'string', 'description' => 'Optional: Neuer Name.'],
                'slug' => ['type' => 'string', 'description' => 'Optional: Neuer Slug.'],
                'description' => ['type' => 'string', 'description' => 'Optional: Neue Beschreibung.'],
                'ort' => ['type' => 'string', 'description' => 'Optional: Neuer Ort (aktualisiert die lokalisiert_in-Beziehung).'],
                'latitude' => ['type' => 'number', 'description' => 'Optional: Breitengrad.'],
                'longitude' => ['type' => 'number', 'description' => 'Optional: Längengrad.'],
                'season' => ['type' => 'string', 'enum' => ['year_round', 'sommer', 'winter', 'event']],
                'status' => ['type' => 'string', 'enum' => ['aktiv', 'saisonal_geschlossen', 'dauerhaft_geschlossen']],
                'source' => ['type' => 'string', 'enum' => ['manuell', 'crowdsourcing', 'import_google', 'import_instagram', 'self_service']],
                'geometry' => ['type' => ['object', 'null'], 'description' => 'Optional: GeoJSON Geometry (Point, LineString, Polygon).'],
                'extra_fields' => ['type' => 'object', 'description' => 'Optional: Typ-spezifische Felder (überschreibt komplett).'],
                'is_active' => ['type' => 'boolean', 'description' => 'Optional: Aktiv/Inaktiv.'],
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

            $entity = SjEntity::where('team_id', $rootTeamId)->find($arguments['entity_id'] ?? 0);
            if (!$entity) {
                return ToolResult::error('NOT_FOUND', 'Entity nicht gefunden.');
            }

            $updatable = ['name', 'slug', 'description', 'latitude', 'longitude', 'geometry', 'season', 'status', 'source', 'is_active'];
            foreach ($updatable as $field) {
                if (array_key_exists($field, $arguments)) {
                    $entity->{$field} = $arguments[$field];
                }
            }
            if (array_key_exists('extra_fields', $arguments)) {
                $entity->extra_fields = is_array($arguments['extra_fields']) ? $arguments['extra_fields'] : null;
            }

            $entity->save();

            // Update lokalisiert_in relationship if ort is provided
            if (array_key_exists('ort', $arguments)) {
                // Remove existing lokalisiert_in relationships
                SjEntityRelationship::where('source_entity_id', $entity->id)
                    ->where('relation_type_id', 1)
                    ->delete();

                if (!empty($arguments['ort'])) {
                    $ortEntity = SjEntity::where('team_id', $rootTeamId)
                        ->whereHas('entityType', fn($q) => $q->where('code', 'ort'))
                        ->where('name', $arguments['ort'])
                        ->first();

                    if ($ortEntity) {
                        SjEntityRelationship::create([
                            'team_id' => $rootTeamId,
                            'source_entity_id' => $entity->id,
                            'target_entity_id' => $ortEntity->id,
                            'relation_type_id' => 1,
                            'is_active' => true,
                        ]);
                    }
                }
            }

            return ToolResult::success([
                'id' => $entity->id,
                'name' => $entity->name,
                'slug' => $entity->slug,
                'status' => $entity->status,
                'message' => 'Entity aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'entities', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
