<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjContentPiece;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class ListContentPiecesTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.content_pieces.GET';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/content-pieces - Listet Content Pieces mit optionalen Filtern (Status, Typ, Suche).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'search' => ['type' => 'string', 'description' => 'Optional: Suche in Titel.'],
                'status' => ['type' => 'string', 'enum' => ['brief', 'draft', 'review', 'published', 'archived'], 'description' => 'Optional: Filter nach Status.'],
                'content_type' => ['type' => 'string', 'enum' => ['guide', 'listicle', 'review', 'event', 'news', 'landing_page'], 'description' => 'Optional: Filter nach Content-Typ.'],
                'entity_id' => ['type' => 'integer', 'description' => 'Optional: Filter nach verknüpfter Entity.'],
                'keyword_id' => ['type' => 'integer', 'description' => 'Optional: Filter nach verknüpftem Keyword.'],
                'sort' => ['type' => 'string', 'enum' => ['title', 'status', 'content_type', 'created_at', 'published_at'], 'description' => 'Sortierung. Default: created_at.'],
                'sort_dir' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'description' => 'Sortierrichtung. Default: desc.'],
                'limit' => ['type' => 'integer', 'description' => 'Max Ergebnisse. Default 50.'],
                'offset' => ['type' => 'integer', 'description' => 'Offset für Pagination. Default 0.'],
            ],
            'required' => [],
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

            $limit = min((int) ($arguments['limit'] ?? 50), 200);
            $offset = (int) ($arguments['offset'] ?? 0);
            $sortField = $arguments['sort'] ?? 'created_at';
            $sortDir = ($arguments['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

            $query = SjContentPiece::where('team_id', $rootTeamId)
                ->with(['coverImage:id,filename,disk_path']);

            if (!empty($arguments['search'])) {
                $query->where('title', 'like', '%' . $arguments['search'] . '%');
            }
            if (!empty($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }
            if (!empty($arguments['content_type'])) {
                $query->where('content_type', $arguments['content_type']);
            }
            if (!empty($arguments['entity_id'])) {
                $query->whereHas('entities', fn($q) => $q->where('sj_entities.id', $arguments['entity_id']));
            }
            if (!empty($arguments['keyword_id'])) {
                $query->whereHas('keywords', fn($q) => $q->where('sj_keywords.id', $arguments['keyword_id']));
            }

            $query->withCount(['keywords', 'entities', 'channelPosts'])
                ->orderBy($sortField, $sortDir);

            $total = $query->count();
            $pieces = $query->offset($offset)->limit($limit)->get();

            return ToolResult::success([
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'content_pieces' => $pieces->map(fn($p) => [
                    'id' => $p->id,
                    'uuid' => $p->uuid,
                    'title' => $p->title,
                    'slug' => $p->slug,
                    'status' => $p->status,
                    'content_type' => $p->content_type,
                    'excerpt' => $p->excerpt,
                    'published_url' => $p->published_url,
                    'published_at' => $p->published_at?->toDateString(),
                    'keywords_count' => $p->keywords_count,
                    'entities_count' => $p->entities_count,
                    'channel_posts_count' => $p->channel_posts_count,
                    'created_at' => $p->created_at->toDateString(),
                ])->toArray(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['syltjunkie', 'content', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'read',
            'idempotent' => true,
        ];
    }
}
