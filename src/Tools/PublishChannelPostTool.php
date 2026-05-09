<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjChannelPost;
use Platform\Syltjunkie\Services\SjPublishingService;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class PublishChannelPostTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.channel_posts.PUBLISH';
    }

    public function getDescription(): string
    {
        return 'POST /syltjunkie/channel-posts/{id}/publish - Veröffentlicht einen Channel Post sofort über den konfigurierten Publisher (Instagram, etc.). ERFORDERLICH: channel_post_id. Post muss Status draft oder scheduled haben.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'channel_post_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Channel-Post-ID.'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
            ],
            'required' => ['channel_post_id'],
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

            $post = SjChannelPost::where('team_id', $rootTeamId)
                ->with(['channel', 'images'])
                ->find($arguments['channel_post_id'] ?? 0);

            if (!$post) {
                return ToolResult::error('NOT_FOUND', 'Channel Post nicht gefunden.');
            }

            if (!in_array($post->status, ['draft', 'scheduled', 'failed'])) {
                return ToolResult::error('INVALID_STATUS', "Post kann nicht veröffentlicht werden (Status: {$post->status}). Erlaubt: draft, scheduled, failed.");
            }

            if (!$post->channel) {
                return ToolResult::error('MISSING_CHANNEL', 'Post hat keinen zugeordneten Channel.');
            }

            $publishingService = app(SjPublishingService::class);
            $result = $publishingService->publish($post);

            $post->refresh();

            return ToolResult::success([
                'id' => $post->id,
                'status' => $post->status,
                'published_at' => $post->published_at?->toDateTimeString(),
                'external_post_id' => $post->external_post_id,
                'success' => $result['success'],
                'error' => $result['error'] ?? null,
                'message' => $result['success']
                    ? 'Post erfolgreich veröffentlicht.'
                    : 'Veröffentlichung fehlgeschlagen: ' . ($result['error'] ?? 'Unbekannter Fehler'),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'channels', 'posts', 'publish'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
