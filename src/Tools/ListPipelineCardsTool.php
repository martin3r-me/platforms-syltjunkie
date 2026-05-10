<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjPipelineCard;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class ListPipelineCardsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.pipeline_cards.GET';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/pipeline_cards - Listet Pipeline-Cards (geplante Entities). Filterbar nach Slot, Status (active/converted) und Entity-Typ.';
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
                'slot_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Nur Cards in diesem Slot.',
                ],
                'slot_name' => [
                    'type' => 'string',
                    'description' => 'Optional: Slot-Name statt ID (z.B. "Idee", "Recherche").',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['active', 'converted'],
                    'description' => 'Optional: "active" (default) oder "converted".',
                ],
                'entity_type_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Nach Entity-Typ filtern.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Optional: Max Ergebnisse (default 50).',
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

            $query = SjPipelineCard::where('team_id', $teamId)
                ->with(['slot:id,name', 'entityType:id,name,icon,color']);

            // Status filter
            $status = $arguments['status'] ?? 'active';
            if ($status === 'active') {
                $query->active();
            } elseif ($status === 'converted') {
                $query->converted();
            }

            // Slot filter
            if (!empty($arguments['slot_id'])) {
                $query->where('slot_id', (int) $arguments['slot_id']);
            } elseif (!empty($arguments['slot_name'])) {
                $slot = \Platform\Syltjunkie\Models\SjPipelineSlot::where('team_id', $teamId)
                    ->where('name', $arguments['slot_name'])->first();
                if ($slot) {
                    $query->where('slot_id', $slot->id);
                }
            }

            // Type filter
            if (!empty($arguments['entity_type_id'])) {
                $query->where('entity_type_id', (int) $arguments['entity_type_id']);
            }

            $limit = min((int) ($arguments['limit'] ?? 50), 200);
            $cards = $query->orderBy('order')->limit($limit)->get();

            return ToolResult::success([
                'cards' => $cards->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'url' => $c->url,
                    'slot' => $c->slot?->name,
                    'entity_type' => $c->entityType?->name,
                    'latitude' => $c->latitude,
                    'longitude' => $c->longitude,
                    'notes' => $c->notes,
                    'converted_at' => $c->converted_at?->toIso8601String(),
                    'converted_entity_id' => $c->converted_entity_id,
                ])->toArray(),
                'total' => $cards->count(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['syltjunkie', 'pipeline', 'cards', 'kanban'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
