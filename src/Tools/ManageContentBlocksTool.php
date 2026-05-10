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

class ManageContentBlocksTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.content_blocks.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /syltjunkie/content-blocks - Setzt alle Content Blocks einer Entity oder eines ContentPieces (Sync-Semantik: löscht bestehende, erstellt neue). ERFORDERLICH: entity_id ODER content_piece_id + blocks-Array. Block-Typen: editorial, faq, stats, quote, highlight, cta_banner, gallery.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity_id' => ['type' => 'integer', 'description' => 'Entity-ID (wenn Entity-Blocks gesetzt werden).'],
                'content_piece_id' => ['type' => 'integer', 'description' => 'ContentPiece-ID (wenn ContentPiece-Blocks gesetzt werden).'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'blocks' => [
                    'type' => 'array',
                    'description' => 'ERFORDERLICH: Array von Blocks. Jeder Block: {block_type, content, order?}.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'block_type' => [
                                'type' => 'string',
                                'enum' => ['editorial', 'faq', 'stats', 'quote', 'highlight', 'cta_banner', 'gallery'],
                                'description' => 'Typ des Blocks.',
                            ],
                            'content' => [
                                'description' => 'Typ-spezifischer Inhalt als JSON. editorial: {body_md}. faq: [{q, a}]. stats: [{label, value, icon}]. quote: {text, author, source?}. highlight: {title, body_md, icon?, color?}. cta_banner: {headline, text?, button_label, url}. gallery: {title?, image_ids: [int]}.',
                            ],
                            'order' => ['type' => 'integer', 'description' => 'Optional: Reihenfolge (Standard: Index im Array).'],
                        ],
                        'required' => ['block_type', 'content'],
                    ],
                ],
            ],
            'required' => ['blocks'],
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

            if ($entityId && $contentPieceId) {
                return ToolResult::error('VALIDATION', 'Nur entity_id ODER content_piece_id angeben, nicht beides.');
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

            $blocks = $arguments['blocks'] ?? [];

            // Delete existing blocks
            SjContentBlock::where('team_id', $rootTeamId)
                ->where('blockable_type', $blockableType)
                ->where('blockable_id', $blockableId)
                ->delete();

            // Create new blocks
            $created = [];
            foreach ($blocks as $i => $block) {
                $record = SjContentBlock::create([
                    'team_id' => $rootTeamId,
                    'blockable_type' => $blockableType,
                    'blockable_id' => $blockableId,
                    'block_type' => $block['block_type'],
                    'content' => $block['content'],
                    'order' => $block['order'] ?? $i,
                    'is_active' => true,
                ]);

                $created[] = [
                    'id' => $record->id,
                    'block_type' => $record->block_type,
                    'order' => $record->order,
                ];
            }

            return ToolResult::success([
                'blocks_count' => count($created),
                'blocks' => $created,
                'message' => count($created) . ' Blocks gesetzt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'content', 'blocks', 'sync'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
