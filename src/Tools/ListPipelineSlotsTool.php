<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjPipelineSlot;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class ListPipelineSlotsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.pipeline_slots.GET';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/pipeline_slots - Listet Pipeline-Slots (Kanban-Spalten) mit Card-Counts auf. Zeigt den Entity-Pipeline-Workflow des Teams.';
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
            $teamId = (int) $resolved['root_team_id'];

            $slots = SjPipelineSlot::where('team_id', $teamId)
                ->withCount(['cards' => fn($q) => $q->whereNull('converted_at')])
                ->orderBy('order')
                ->get();

            return ToolResult::success([
                'slots' => $slots->map(fn($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'color' => $s->color,
                    'order' => $s->order,
                    'active_cards_count' => $s->cards_count,
                ])->toArray(),
                'total_slots' => $slots->count(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['syltjunkie', 'pipeline', 'slots', 'kanban'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
