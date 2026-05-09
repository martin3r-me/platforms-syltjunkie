<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjChannelPost;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class CreateChannelPostTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.channel_posts.POST';
    }

    public function getDescription(): string
    {
        return 'POST /syltjunkie/channel-posts - Erstellt einen Channel Post. ERFORDERLICH: channel_id, post_type, caption. Optional: entity_id, content_piece_id, image_ids, hashtags, scheduled_at.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'channel_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Channel-ID (syltjunkie.channels.GET).'],
                'post_type' => [
                    'type' => 'string',
                    'enum' => ['image', 'carousel', 'reel', 'story', 'article', 'post'],
                    'description' => 'ERFORDERLICH: Post-Typ.',
                ],
                'caption' => ['type' => 'string', 'description' => 'ERFORDERLICH: Caption/Text des Posts.'],
                'entity_id' => ['type' => 'integer', 'description' => 'Optional: Verknüpfte Entity-ID.'],
                'content_piece_id' => ['type' => 'integer', 'description' => 'Optional: Verknüpftes Content Piece.'],
                'hashtags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: Hashtags als Array (ohne #).',
                ],
                'image_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Optional: SjImage-IDs für den Post (Reihenfolge = sort_order).',
                ],
                'scheduled_at' => ['type' => 'string', 'description' => 'Optional: Geplante Veröffentlichung (ISO 8601). Ohne = draft.'],
                'meta_data' => ['type' => 'object', 'description' => 'Optional: Zusätzliche Meta-Daten als JSON.'],
            ],
            'required' => ['channel_id', 'post_type', 'caption'],
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

            if (empty($arguments['channel_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'channel_id ist erforderlich.');
            }
            if (empty($arguments['post_type'])) {
                return ToolResult::error('VALIDATION_ERROR', 'post_type ist erforderlich.');
            }
            $caption = trim((string) ($arguments['caption'] ?? ''));
            if ($caption === '') {
                return ToolResult::error('VALIDATION_ERROR', 'caption ist erforderlich.');
            }

            $status = 'draft';
            $scheduledAt = null;
            if (!empty($arguments['scheduled_at'])) {
                $scheduledAt = $arguments['scheduled_at'];
                $status = 'scheduled';
            }

            $post = SjChannelPost::create([
                'team_id' => $rootTeamId,
                'channel_id' => (int) $arguments['channel_id'],
                'post_type' => $arguments['post_type'],
                'caption' => $caption,
                'status' => $status,
                'entity_id' => $arguments['entity_id'] ?? null,
                'content_piece_id' => $arguments['content_piece_id'] ?? null,
                'hashtags' => $arguments['hashtags'] ?? null,
                'meta_data' => (isset($arguments['meta_data']) && is_array($arguments['meta_data'])) ? $arguments['meta_data'] : null,
                'scheduled_at' => $scheduledAt,
                'created_by' => $context->user?->id,
            ]);

            // Attach images
            if (!empty($arguments['image_ids']) && is_array($arguments['image_ids'])) {
                $imageSync = [];
                foreach ($arguments['image_ids'] as $i => $imgId) {
                    $imageSync[(int) $imgId] = ['sort_order' => $i, 'role' => $i === 0 ? 'cover' : 'slide'];
                }
                $post->images()->attach($imageSync);
            }

            return ToolResult::success([
                'id' => $post->id,
                'uuid' => $post->uuid,
                'status' => $post->status,
                'post_type' => $post->post_type,
                'channel_id' => $post->channel_id,
                'scheduled_at' => $post->scheduled_at?->toDateTimeString(),
                'message' => 'Channel Post erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'channels', 'posts', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
