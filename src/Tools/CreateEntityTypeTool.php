<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjEntityType;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class CreateEntityTypeTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.entity_types.POST';
    }

    public function getDescription(): string
    {
        return 'POST /syltjunkie/entity-types - Erstellt einen neuen Entity-Type. ERFORDERLICH: name, code, group_id.';
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
                    'description' => 'ERFORDERLICH: Name des Types (z.B. "Restaurant", "Hotel").',
                ],
                'code' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Eindeutiger Code (z.B. "restaurant", "hotel").',
                ],
                'group_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Entity-Type-Gruppe.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung.',
                ],
                'icon' => [
                    'type' => 'string',
                    'description' => 'Optional: Icon-Name (Heroicon).',
                ],
                'sort_order' => [
                    'type' => 'integer',
                    'description' => 'Optional: Sortierreihenfolge. Default: 0.',
                ],
                'extra_field_schema' => [
                    'type' => 'object',
                    'description' => 'Optional: JSON-Schema für typ-spezifische Extra-Felder.',
                ],
            ],
            'required' => ['name', 'code', 'group_id'],
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
            $code = trim((string) ($arguments['code'] ?? ''));

            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }
            if ($code === '') {
                return ToolResult::error('VALIDATION_ERROR', 'code ist erforderlich.');
            }
            if (empty($arguments['group_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'group_id ist erforderlich.');
            }

            $existing = SjEntityType::where('team_id', $rootTeamId)->where('code', $code)->first();
            if ($existing) {
                return ToolResult::error('DUPLICATE', "Entity-Type mit code '{$code}' existiert bereits (ID: {$existing->id}).");
            }

            $type = SjEntityType::create([
                'team_id' => $rootTeamId,
                'group_id' => (int) $arguments['group_id'],
                'name' => $name,
                'code' => $code,
                'description' => ($arguments['description'] ?? null) ?: null,
                'icon' => $arguments['icon'] ?? null,
                'sort_order' => $arguments['sort_order'] ?? 0,
                'extra_field_schema' => (isset($arguments['extra_field_schema']) && is_array($arguments['extra_field_schema'])) ? $arguments['extra_field_schema'] : null,
                'is_active' => true,
            ]);

            return ToolResult::success([
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'group_id' => $type->group_id,
                'icon' => $type->icon,
                'message' => 'Entity-Type erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'entity_types', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
