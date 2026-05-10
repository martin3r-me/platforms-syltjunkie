<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjImage;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class ListImagesTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.images.GET';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/images - Listet Bilder der Bilddatenbank. Filter nach Tag, Entity, unused. Suche in title, description, photographer.';
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
                'search' => [
                    'type' => 'string',
                    'description' => 'Optional: Suche in title, description, photographer.',
                ],
                'tag' => [
                    'type' => 'string',
                    'description' => 'Optional: Filter nach Tag (z.B. "strand", "restaurant").',
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Nur Bilder einer bestimmten Entity.',
                ],
                'unused' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Nur Bilder ohne Entity-Zuordnung.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Optional: Max. Ergebnisse (default 20, max 100).',
                    'default' => 20,
                ],
                'offset' => [
                    'type' => 'integer',
                    'description' => 'Optional: Offset für Pagination.',
                    'default' => 0,
                ],
            ],
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

            $q = SjImage::query()
                ->where('team_id', $rootTeamId)
                ->with('contextFile')
                ->withCount('entities');

            if (!empty($arguments['search'])) {
                $search = $arguments['search'];
                $q->where(function ($sub) use ($search) {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('photographer', 'like', "%{$search}%");
                });
            }

            if (!empty($arguments['tag'])) {
                $q->withTag($arguments['tag']);
            }

            if (!empty($arguments['entity_id'])) {
                $q->whereHas('entities', fn($sub) => $sub->where('sj_entities.id', (int) $arguments['entity_id']));
            }

            if (!empty($arguments['unused'])) {
                $q->doesntHave('entities');
            }

            $limit = min((int) ($arguments['limit'] ?? 20), 100);
            $offset = (int) ($arguments['offset'] ?? 0);
            $total = $q->count();

            $images = $q->orderBy('created_at', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();

            $items = $images->map(fn($img) => [
                'id' => $img->id,
                'uuid' => $img->uuid,
                'title' => $img->title,
                'photographer' => $img->photographer,
                'tags' => $img->tags,
                'latitude' => $img->latitude,
                'longitude' => $img->longitude,
                'taken_at' => $img->taken_at?->toDateString(),
                'url' => $img->url,
                'thumbnail_url' => $img->thumbnail_url,
                'entity_count' => $img->entities_count,
            ])->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                ],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['syltjunkie', 'images', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
