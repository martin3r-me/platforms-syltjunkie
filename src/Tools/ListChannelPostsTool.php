<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjChannelPost;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class ListChannelPostsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.channel_posts.GET';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/channel-posts - Listet Channel Posts mit optionalen Filtern (Status, Channel, Entity).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'status' => ['type' => 'string', 'enum' => ['draft', 'scheduled', 'publishing', 'published', 'failed'], 'description' => 'Optional: Filter nach Status.'],
                'channel_id' => ['type' => 'integer', 'description' => 'Optional: Filter nach Channel.'],
                'entity_id' => ['type' => 'integer', 'description' => 'Optional: Filter nach Entity.'],
                'content_piece_id' => ['type' => 'integer', 'description' => 'Optional: Filter nach Content Piece.'],
                'post_type' => ['type' => 'string', 'enum' => ['image', 'carousel', 'reel', 'story', 'article', 'post'], 'description' => 'Optional: Filter nach Post-Typ.'],
                'sort' => ['type' => 'string', 'enum' => ['created_at', 'scheduled_at', 'published_at'], 'description' => 'Sortierung. Default: created_at.'],
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

            $query = SjChannelPost::where('team_id', $rootTeamId)
                ->with([
                    'channel:id,name,type',
                    'entity:id,name',
                    'contentPiece:id,title,status',
                ]);

            if (!empty($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }
            if (!empty($arguments['channel_id'])) {
                $query->where('channel_id', $arguments['channel_id']);
            }
            if (!empty($arguments['entity_id'])) {
                $query->where('entity_id', $arguments['entity_id']);
            }
            if (!empty($arguments['content_piece_id'])) {
                $query->where('content_piece_id', $arguments['content_piece_id']);
            }
            if (!empty($arguments['post_type'])) {
                $query->where('post_type', $arguments['post_type']);
            }

            $query->orderBy($sortField, $sortDir);

            $total = $query->count();
            $posts = $query->offset($offset)->limit($limit)->get();

            return ToolResult::success([
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'channel_posts' => $posts->map(fn($p) => [
                    'id' => $p->id,
                    'uuid' => $p->uuid,
                    'status' => $p->status,
                    'post_type' => $p->post_type,
                    'caption' => $p->caption,
                    'hashtags' => $p->hashtags,
                    'channel' => $p->channel ? ['id' => $p->channel->id, 'name' => $p->channel->name, 'type' => $p->channel->type] : null,
                    'entity' => $p->entity ? ['id' => $p->entity->id, 'name' => $p->entity->name] : null,
                    'content_piece' => $p->contentPiece ? ['id' => $p->contentPiece->id, 'title' => $p->contentPiece->title] : null,
                    'scheduled_at' => $p->scheduled_at?->toDateTimeString(),
                    'published_at' => $p->published_at?->toDateTimeString(),
                    'external_post_id' => $p->external_post_id,
                    'error_message' => $p->error_message,
                    'created_at' => $p->created_at->toDateTimeString(),
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
            'tags' => ['syltjunkie', 'channels', 'posts', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'read',
            'idempotent' => true,
        ];
    }
}
