<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityUrl;
use Platform\Syltjunkie\Models\SjPipelineCard;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class ConvertPipelineCardTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.pipeline_cards.convert';
    }

    public function getDescription(): string
    {
        return 'Konvertiert eine Pipeline-Card in eine echte SjEntity. ERFORDERLICH: card_id. Card muss name + entity_type_id gesetzt haben. Erstellt Entity + optional EntityUrl (website).';
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
                    'description' => 'ERFORDERLICH: ID der zu konvertierenden Card.',
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

            if (empty($card->name) || empty($card->entity_type_id)) {
                return ToolResult::error('VALIDATION_ERROR', 'Card muss name und entity_type_id gesetzt haben.');
            }

            $entity = SjEntity::create([
                'team_id' => $teamId,
                'name' => $card->name,
                'entity_type_id' => $card->entity_type_id,
                'latitude' => $card->latitude,
                'longitude' => $card->longitude,
                'source' => 'manuell',
                'status' => 'aktiv',
                'is_active' => true,
            ]);

            if ($card->url) {
                SjEntityUrl::create([
                    'team_id' => $teamId,
                    'entity_id' => $entity->id,
                    'url' => $card->url,
                    'platform' => 'website',
                    'is_primary' => true,
                    'is_active' => true,
                ]);
            }

            $card->update([
                'converted_at' => now(),
                'converted_entity_id' => $entity->id,
            ]);

            return ToolResult::success([
                'card_id' => $card->id,
                'entity_id' => $entity->id,
                'entity_uuid' => $entity->uuid,
                'entity_name' => $entity->name,
                'message' => "Card \"{$card->name}\" erfolgreich in Entity konvertiert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'pipeline', 'cards', 'convert', 'entity'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
