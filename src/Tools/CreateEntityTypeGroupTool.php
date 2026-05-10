<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjEntityTypeGroup;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class CreateEntityTypeGroupTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.entity_type_groups.POST';
    }

    public function getDescription(): string
    {
        return 'POST /syltjunkie/entity-type-groups - Erstellt eine neue Entity-Type-Gruppe. ERFORDERLICH: name, code.';
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
                    'description' => 'ERFORDERLICH: Name der Gruppe (z.B. "Gastronomie", "Unterkünfte").',
                ],
                'code' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Eindeutiger Code (z.B. "gastronomy", "accommodation").',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung der Gruppe.',
                ],
                'icon' => [
                    'type' => 'string',
                    'description' => 'Optional: Icon-Name (Heroicon). Default: folder.',
                ],
                'sort_order' => [
                    'type' => 'integer',
                    'description' => 'Optional: Sortierreihenfolge. Default: 0.',
                ],
                'show_on_map' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Auf Karte anzeigen. Default: true.',
                ],
            ],
            'required' => ['name', 'code'],
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

            $existing = SjEntityTypeGroup::where('team_id', $rootTeamId)->where('code', $code)->first();
            if ($existing) {
                return ToolResult::error('DUPLICATE', "Gruppe mit code '{$code}' existiert bereits (ID: {$existing->id}).");
            }

            $group = SjEntityTypeGroup::create([
                'team_id' => $rootTeamId,
                'name' => $name,
                'code' => $code,
                'description' => ($arguments['description'] ?? null) ?: null,
                'icon' => $arguments['icon'] ?? 'folder',
                'sort_order' => $arguments['sort_order'] ?? 0,
                'show_on_map' => $arguments['show_on_map'] ?? true,
                'is_active' => true,
            ]);

            return ToolResult::success([
                'id' => $group->id,
                'code' => $group->code,
                'name' => $group->name,
                'icon' => $group->icon,
                'sort_order' => $group->sort_order,
                'message' => 'Entity-Type-Gruppe erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'entity_type_groups', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
