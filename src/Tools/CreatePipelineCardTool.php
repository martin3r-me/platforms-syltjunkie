<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjPipelineCard;
use Platform\Syltjunkie\Models\SjPipelineSlot;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class CreatePipelineCardTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.pipeline_cards.POST';
    }

    public function getDescription(): string
    {
        return 'POST /syltjunkie/pipeline_cards - Erstellt eine Pipeline-Card (geplante Entity). ERFORDERLICH: name. Optional: slot_name oder slot_id, url, entity_type_id, latitude, longitude, notes.';
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
                'name' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Name der geplanten Entity.',
                ],
                'slot_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Slot-ID.',
                ],
                'slot_name' => [
                    'type' => 'string',
                    'description' => 'Optional: Slot-Name (z.B. "Idee", "Recherche", "Bereit"). Alternative zu slot_id.',
                ],
                'url' => [
                    'type' => 'string',
                    'description' => 'Optional: Website-URL der Entity.',
                ],
                'entity_type_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Entity-Typ ID.',
                ],
                'latitude' => [
                    'type' => 'number',
                    'description' => 'Optional: Breitengrad.',
                ],
                'longitude' => [
                    'type' => 'number',
                    'description' => 'Optional: Längengrad.',
                ],
                'notes' => [
                    'type' => 'string',
                    'description' => 'Optional: Freitext-Notizen.',
                ],
            ],
            'required' => ['name'],
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

            $name = trim((string) ($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            // Resolve slot
            $slotId = null;
            if (!empty($arguments['slot_id'])) {
                $slotId = (int) $arguments['slot_id'];
            } elseif (!empty($arguments['slot_name'])) {
                $slot = SjPipelineSlot::where('team_id', $teamId)
                    ->where('name', $arguments['slot_name'])->first();
                if ($slot) {
                    $slotId = $slot->id;
                }
            }

            $card = SjPipelineCard::create([
                'team_id' => $teamId,
                'slot_id' => $slotId,
                'name' => $name,
                'url' => ($arguments['url'] ?? null) ?: null,
                'entity_type_id' => !empty($arguments['entity_type_id']) ? (int) $arguments['entity_type_id'] : null,
                'latitude' => $arguments['latitude'] ?? null,
                'longitude' => $arguments['longitude'] ?? null,
                'notes' => ($arguments['notes'] ?? null) ?: null,
            ]);

            $card->load('slot:id,name', 'entityType:id,name');

            return ToolResult::success([
                'id' => $card->id,
                'uuid' => $card->uuid,
                'name' => $card->name,
                'slot' => $card->slot?->name,
                'entity_type' => $card->entityType?->name,
                'message' => 'Pipeline-Card erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'pipeline', 'cards', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
