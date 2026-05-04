<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjEntityUrl;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class UpdateEntityUrlTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.entity_urls.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /syltjunkie/entity_urls/{id} - Aktualisiert eine Entity-URL. ERFORDERLICH: entity_url_id. Alle anderen Felder optional.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity_url_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Entity-URL.',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID.',
                ],
                'url' => ['type' => 'string', 'description' => 'Optional: Neue URL.'],
                'platform' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Platform-Typ.',
                    'enum' => ['website', 'google_maps', 'tripadvisor', 'instagram', 'facebook', 'booking', 'yelp', 'other'],
                ],
                'is_primary' => ['type' => 'boolean', 'description' => 'Optional: Haupt-URL setzen.'],
                'is_active' => ['type' => 'boolean', 'description' => 'Optional: Aktiv/Inaktiv.'],
            ],
            'required' => ['entity_url_id'],
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

            $entityUrl = SjEntityUrl::where('team_id', $rootTeamId)->find($arguments['entity_url_id'] ?? 0);
            if (!$entityUrl) {
                return ToolResult::error('NOT_FOUND', 'Entity-URL nicht gefunden.');
            }

            // If setting as primary, unset other primaries for same entity
            if (isset($arguments['is_primary']) && $arguments['is_primary']) {
                SjEntityUrl::where('team_id', $rootTeamId)
                    ->where('entity_id', $entityUrl->entity_id)
                    ->where('id', '!=', $entityUrl->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            $updatable = ['url', 'platform', 'is_primary', 'is_active'];
            foreach ($updatable as $field) {
                if (array_key_exists($field, $arguments)) {
                    $entityUrl->{$field} = $arguments[$field];
                }
            }

            $entityUrl->save();

            return ToolResult::success([
                'id' => $entityUrl->id,
                'url' => $entityUrl->url,
                'platform' => $entityUrl->platform,
                'is_primary' => $entityUrl->is_primary,
                'is_active' => $entityUrl->is_active,
                'message' => 'Entity-URL aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'entity_urls', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
