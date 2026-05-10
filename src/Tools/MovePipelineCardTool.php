<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjPipelineCard;
use Platform\Syltjunkie\Models\SjPipelineSlot;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class MovePipelineCardTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.pipeline_cards.move';
    }

    public function getDescription(): string
    {
        return 'Verschiebt eine Pipeline-Card in einen anderen Slot. ERFORDERLICH: card_id + (slot_id ODER slot_name). Beispiel: "verschiebe Card nach Recherche".';
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
                'card_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Card.',
                ],
                'slot_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Ziel-Slot ID.',
                ],
                'slot_name' => [
                    'type' => 'string',
                    'description' => 'Optional: Ziel-Slot Name (z.B. "Recherche", "Bereit"). Alternative zu slot_id.',
                ],
            ],
            'required' => ['card_id'],
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

            if (empty($arguments['card_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'card_id ist erforderlich.');
            }

            $card = SjPipelineCard::where('team_id', $teamId)->active()->find((int) $arguments['card_id']);
            if (!$card) {
                return ToolResult::error('NOT_FOUND', 'Card nicht gefunden oder bereits konvertiert.');
            }

            // Resolve target slot
            $slotId = null;
            if (!empty($arguments['slot_id'])) {
                $slot = SjPipelineSlot::where('team_id', $teamId)->find((int) $arguments['slot_id']);
                if (!$slot) {
                    return ToolResult::error('NOT_FOUND', 'Ziel-Slot nicht gefunden.');
                }
                $slotId = $slot->id;
            } elseif (!empty($arguments['slot_name'])) {
                $slot = SjPipelineSlot::where('team_id', $teamId)
                    ->where('name', $arguments['slot_name'])->first();
                if (!$slot) {
                    return ToolResult::error('NOT_FOUND', "Slot \"{$arguments['slot_name']}\" nicht gefunden.");
                }
                $slotId = $slot->id;
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'slot_id oder slot_name ist erforderlich.');
            }

            $card->update(['slot_id' => $slotId]);
            $card->load('slot:id,name');

            return ToolResult::success([
                'id' => $card->id,
                'name' => $card->name,
                'slot' => $card->slot->name,
                'message' => "Card \"{$card->name}\" nach \"{$card->slot->name}\" verschoben.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'pipeline', 'cards', 'move'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
