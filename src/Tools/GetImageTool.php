<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjImage;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class GetImageTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.image.GET';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/images/{id} - Ruft ein einzelnes Bild ab inkl. verknüpfter Entities. ERFORDERLICH: image_id.';
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

            $image = SjImage::where('team_id', $rootTeamId)
                ->with(['contextFile', 'entities'])
                ->find($arguments['image_id'] ?? 0);

            if (!$image) {
                return ToolResult::error('NOT_FOUND', 'Bild nicht gefunden.');
            }

            $data = [
                'id' => $image->id,
                'uuid' => $image->uuid,
                'title' => $image->title,
                'description' => $image->description,
                'photographer' => $image->photographer,
                'tags' => $image->tags,
                'latitude' => $image->latitude,
                'longitude' => $image->longitude,
                'taken_at' => $image->taken_at?->toDateString(),
                'url' => $image->url,
                'thumbnail_url' => $image->thumbnail_url,
                'entities' => $image->entities->map(fn($e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'slug' => $e->slug,
                    'is_primary' => (bool) $e->pivot->is_primary,
                    'sort_order' => $e->pivot->sort_order,
                    'source' => $e->pivot->source,
                ])->toArray(),
            ];

            return ToolResult::success($data);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['syltjunkie', 'images', 'detail'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
