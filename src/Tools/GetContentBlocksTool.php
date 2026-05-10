<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjContentBlock;
use Platform\Syltjunkie\Models\SjContentPiece;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class GetContentBlocksTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.content_blocks.GET';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/content-blocks - Gibt alle Content Blocks einer Entity oder eines ContentPieces zurück, sortiert nach order. ERFORDERLICH: entity_id ODER content_piece_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity_id' => ['type' => 'integer', 'description' => 'Entity-ID (wenn Entity-Blocks abgerufen werden).'],
                'content_piece_id' => ['type' => 'integer', 'description' => 'ContentPiece-ID (wenn ContentPiece-Blocks abgerufen werden).'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
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

            $entityId = $arguments['entity_id'] ?? null;
            $contentPieceId = $arguments['content_piece_id'] ?? null;

            if (!$entityId && !$contentPieceId) {
                return ToolResult::error('VALIDATION', 'entity_id oder content_piece_id ist erforderlich.');
            }

            if ($entityId) {
                $parent = SjEntity::where('team_id', $rootTeamId)->find($entityId);
                if (!$parent) {
                    return ToolResult::error('NOT_FOUND', 'Entity nicht gefunden.');
                }
                $blockableType = SjEntity::class;
                $blockableId = $entityId;
            } else {
                $parent = SjContentPiece::where('team_id', $rootTeamId)->find($contentPieceId);
                if (!$parent) {
                    return ToolResult::error('NOT_FOUND', 'ContentPiece nicht gefunden.');
                }
                $blockableType = SjContentPiece::class;
                $blockableId = $contentPieceId;
            }

            $blocks = SjContentBlock::where('team_id', $rootTeamId)
                ->where('blockable_type', $blockableType)
                ->where('blockable_id', $blockableId)
                ->orderBy('order')
                ->get();

            return ToolResult::success([
                'blocks' => $blocks->map(fn ($b) => [
                    'id' => $b->id,
                    'block_type' => $b->block_type,
                    'content' => $b->content,
                    'order' => $b->order,
                    'is_active' => $b->is_active,
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
            'tags' => ['syltjunkie', 'content', 'blocks'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'read',
            'idempotent' => true,
        ];
    }
}
