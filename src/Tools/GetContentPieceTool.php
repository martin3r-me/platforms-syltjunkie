<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjContentPiece;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class GetContentPieceTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.content_pieces.GET_ONE';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/content-pieces/{id} - Gibt ein Content Piece mit allen Details inkl. Body, Keywords, Entities und Channel Posts zurück.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content_piece_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Content-Piece-ID.'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
            ],
            'required' => ['content_piece_id'],
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

            $piece = SjContentPiece::where('team_id', $rootTeamId)
                ->with([
                    'keywords:id,keyword,search_volume,search_intent',
                    'entities:id,name',
                    'channelPosts:id,channel_id,status,post_type,published_at',
                    'channelPosts.channel:id,name,type',
                    'coverImage:id,filename,disk_path',
                ])
                ->find($arguments['content_piece_id'] ?? 0);

            if (!$piece) {
                return ToolResult::error('NOT_FOUND', 'Content Piece nicht gefunden.');
            }

            return ToolResult::success([
                'id' => $piece->id,
                'uuid' => $piece->uuid,
                'title' => $piece->title,
                'slug' => $piece->slug,
                'status' => $piece->status,
                'content_type' => $piece->content_type,
                'brief_notes' => $piece->brief_notes,
                'body_markdown' => $piece->body_markdown,
                'excerpt' => $piece->excerpt,
                'seo_title' => $piece->seo_title,
                'seo_description' => $piece->seo_description,
                'published_url' => $piece->published_url,
                'published_at' => $piece->published_at?->toDateTimeString(),
                'target_traffic_estimate' => $piece->target_traffic_estimate,
                'target_value_euro' => $piece->target_value_euro,
                'cover_image' => $piece->coverImage ? [
                    'id' => $piece->coverImage->id,
                    'filename' => $piece->coverImage->filename,
                ] : null,
                'keywords' => $piece->keywords->map(fn($k) => [
                    'id' => $k->id,
                    'keyword' => $k->keyword,
                    'search_volume' => $k->search_volume,
                    'search_intent' => $k->search_intent,
                    'is_primary' => (bool) $k->pivot->is_primary,
                ])->toArray(),
                'entities' => $piece->entities->map(fn($e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'ort' => $e->ortEntity()?->name,
                    'is_primary' => (bool) $e->pivot->is_primary,
                    'display_order' => $e->pivot->display_order,
                ])->toArray(),
                'channel_posts' => $piece->channelPosts->map(fn($p) => [
                    'id' => $p->id,
                    'channel' => $p->channel ? ['id' => $p->channel->id, 'name' => $p->channel->name, 'type' => $p->channel->type] : null,
                    'status' => $p->status,
                    'post_type' => $p->post_type,
                    'published_at' => $p->published_at?->toDateTimeString(),
                ])->toArray(),
                'created_at' => $piece->created_at->toDateTimeString(),
                'updated_at' => $piece->updated_at->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['syltjunkie', 'content', 'detail'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'read',
            'idempotent' => true,
        ];
    }
}
