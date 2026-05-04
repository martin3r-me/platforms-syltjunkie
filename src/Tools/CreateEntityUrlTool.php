<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityUrl;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class CreateEntityUrlTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.entity_urls.POST';
    }

    public function getDescription(): string
    {
        return 'POST /syltjunkie/entity_urls - Legt eine Seed-URL für eine Entity an. ERFORDERLICH: entity_id, url. Optional: platform, is_primary.';
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
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Entity.',
                ],
                'url' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Die URL (z.B. "https://www.gosch.de").',
                ],
                'platform' => [
                    'type' => 'string',
                    'description' => 'Optional: Platform-Typ. Default: website.',
                    'enum' => ['website', 'google_maps', 'tripadvisor', 'instagram', 'facebook', 'booking', 'yelp', 'other'],
                    'default' => 'website',
                ],
                'is_primary' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Ist dies die Haupt-URL der Entity? Default: false.',
                    'default' => false,
                ],
            ],
            'required' => ['entity_id', 'url'],
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

            if (empty($arguments['entity_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'entity_id ist erforderlich.');
            }

            $url = trim((string) ($arguments['url'] ?? ''));
            if ($url === '') {
                return ToolResult::error('VALIDATION_ERROR', 'url ist erforderlich.');
            }

            $entity = SjEntity::where('team_id', $rootTeamId)->find((int) $arguments['entity_id']);
            if (!$entity) {
                return ToolResult::error('NOT_FOUND', 'Entity nicht gefunden.');
            }

            $isPrimary = (bool) ($arguments['is_primary'] ?? false);

            // If setting as primary, unset other primaries for same entity+platform
            if ($isPrimary) {
                SjEntityUrl::where('team_id', $rootTeamId)
                    ->where('entity_id', $entity->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            $entityUrl = SjEntityUrl::create([
                'team_id' => $rootTeamId,
                'entity_id' => $entity->id,
                'url' => $url,
                'platform' => $arguments['platform'] ?? 'website',
                'is_primary' => $isPrimary,
                'is_active' => true,
            ]);

            return ToolResult::success([
                'id' => $entityUrl->id,
                'uuid' => $entityUrl->uuid,
                'entity_id' => $entityUrl->entity_id,
                'entity_name' => $entity->name,
                'url' => $entityUrl->url,
                'platform' => $entityUrl->platform,
                'is_primary' => $entityUrl->is_primary,
                'message' => 'Entity-URL erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'entity_urls', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
