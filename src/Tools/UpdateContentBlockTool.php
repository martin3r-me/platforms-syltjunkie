<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjContentBlock;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class UpdateContentBlockTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.content_block.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /syltjunkie/content-blocks/{id} - Aktualisiert einen einzelnen Content Block (Content, Order, Aktivstatus). ERFORDERLICH: block_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'block_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Block-ID.'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'content' => ['description' => 'Optional: Neuer Content (JSON, typ-spezifisch).'],
                'order' => ['type' => 'integer', 'description' => 'Optional: Neue Reihenfolge.'],
                'is_active' => ['type' => 'boolean', 'description' => 'Optional: Aktiv/Inaktiv setzen.'],
            ],
            'required' => ['block_id'],
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

            $block = SjContentBlock::where('team_id', $rootTeamId)
                ->find($arguments['block_id'] ?? 0);

            if (!$block) {
                return ToolResult::error('NOT_FOUND', 'Block nicht gefunden.');
            }

            $updatable = ['content', 'order', 'is_active'];
            foreach ($updatable as $field) {
                if (array_key_exists($field, $arguments)) {
                    $block->{$field} = $arguments[$field];
                }
            }

            $block->save();

            return ToolResult::success([
                'id' => $block->id,
                'block_type' => $block->block_type,
                'content' => $block->content,
                'order' => $block->order,
                'is_active' => $block->is_active,
                'message' => 'Block aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'content', 'blocks', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
