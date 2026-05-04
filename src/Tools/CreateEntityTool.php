<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class CreateEntityTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.entities.POST';
    }

    public function getDescription(): string
    {
        return 'POST /syltjunkie/entities - Erstellt eine Syltjunkie-Entity (Restaurant, Hotel, Strand, ...). ERFORDERLICH: name, entity_type_id. Nutze syltjunkie.entity_types.GET um die Type-ID zu ermitteln.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Name der Entity (z.B. "Sansibar", "Hotel Miramar").',
                ],
                'entity_type_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: Entity-Type ID. Nutze syltjunkie.entity_types.GET.',
                ],
                'slug' => [
                    'type' => 'string',
                    'description' => 'Optional: URL-Slug. Wird automatisch aus name generiert wenn leer.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung der Entity.',
                ],
                'ort' => [
                    'type' => 'string',
                    'description' => 'Optional: Ortschaft auf Sylt (z.B. "Westerland", "Kampen", "Rantum", "List", "Hörnum", "Wenningstedt", "Keitum", "Tinnum", "Archsum", "Morsum").',
                ],
                'latitude' => [
                    'type' => 'number',
                    'description' => 'Optional: Breitengrad (z.B. 54.9079).',
                ],
                'longitude' => [
                    'type' => 'number',
                    'description' => 'Optional: Längengrad (z.B. 8.3047).',
                ],
                'season' => [
                    'type' => 'string',
                    'description' => 'Optional: Saison. Default: year_round.',
                    'enum' => ['year_round', 'sommer', 'winter', 'event'],
                    'default' => 'year_round',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Optional: Status. Default: aktiv.',
                    'enum' => ['aktiv', 'saisonal_geschlossen', 'dauerhaft_geschlossen'],
                    'default' => 'aktiv',
                ],
                'source' => [
                    'type' => 'string',
                    'description' => 'Optional: Datenquelle. Default: manuell.',
                    'enum' => ['manuell', 'crowdsourcing', 'import_google', 'import_instagram', 'self_service'],
                    'default' => 'manuell',
                ],
                'extra_fields' => [
                    'type' => 'object',
                    'description' => 'Optional: Typ-spezifische Felder als JSON (z.B. {"cuisine": ["seafood"], "price_class": "€€€", "michelin_stars": 0}). Schema: syltjunkie.entity_types.GET mit include_schema=true.',
                ],
            ],
            'required' => ['name', 'entity_type_id'],
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

            $name = trim((string) ($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }
            if (empty($arguments['entity_type_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'entity_type_id ist erforderlich.');
            }

            $entity = SjEntity::create([
                'team_id' => $rootTeamId,
                'entity_type_id' => (int) $arguments['entity_type_id'],
                'name' => $name,
                'slug' => !empty($arguments['slug']) ? $arguments['slug'] : null,
                'description' => ($arguments['description'] ?? null) ?: null,
                'ort' => ($arguments['ort'] ?? null) ?: null,
                'latitude' => $arguments['latitude'] ?? null,
                'longitude' => $arguments['longitude'] ?? null,
                'season' => $arguments['season'] ?? 'year_round',
                'status' => $arguments['status'] ?? 'aktiv',
                'source' => $arguments['source'] ?? 'manuell',
                'extra_fields' => (isset($arguments['extra_fields']) && is_array($arguments['extra_fields'])) ? $arguments['extra_fields'] : null,
                'is_active' => true,
            ]);

            return ToolResult::success([
                'id' => $entity->id,
                'uuid' => $entity->uuid,
                'name' => $entity->name,
                'slug' => $entity->slug,
                'entity_type_id' => $entity->entity_type_id,
                'ort' => $entity->ort,
                'status' => $entity->status,
                'message' => 'Entity erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'entities', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
