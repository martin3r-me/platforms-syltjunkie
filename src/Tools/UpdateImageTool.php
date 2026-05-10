<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjImage;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class UpdateImageTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.images.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /syltjunkie/images/{id} - Aktualisiert Bild-Metadaten (title, description, photographer, tags). ERFORDERLICH: image_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'image_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Bildes.',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID.',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Titel.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Beschreibung.',
                ],
                'photographer' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Fotograf.',
                ],
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: Tags (überschreibt komplett).',
                ],
            ],
            'required' => ['image_id'],
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

            $image = SjImage::where('team_id', $rootTeamId)->find($arguments['image_id'] ?? 0);
            if (!$image) {
                return ToolResult::error('NOT_FOUND', 'Bild nicht gefunden.');
            }

            $updatable = ['title', 'description', 'photographer'];
            foreach ($updatable as $field) {
                if (array_key_exists($field, $arguments)) {
                    $image->{$field} = $arguments[$field];
                }
            }

            if (array_key_exists('tags', $arguments)) {
                $image->tags = is_array($arguments['tags']) ? $arguments['tags'] : null;
            }

            $image->save();

            return ToolResult::success([
                'id' => $image->id,
                'title' => $image->title,
                'tags' => $image->tags,
                'message' => 'Bild aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'images', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
