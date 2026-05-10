<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjPipelineCard;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class UpdatePipelineCardTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.pipeline_cards.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /syltjunkie/pipeline_cards - Aktualisiert eine Pipeline-Card. ERFORDERLICH: card_id. Alle anderen Felder optional.';
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
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Name.',
                ],
                'url' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue URL. Leerer String entfernt die URL.',
                ],
                'entity_type_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neuer Entity-Typ. 0 oder null entfernt den Typ.',
                ],
                'latitude' => [
                    'type' => 'number',
                    'description' => 'Optional: Neuer Breitengrad.',
                ],
                'longitude' => [
                    'type' => 'number',
                    'description' => 'Optional: Neuer Längengrad.',
                ],
                'notes' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Notizen.',
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

            $data = [];
            if (array_key_exists('name', $arguments) && trim($arguments['name']) !== '') {
                $data['name'] = trim($arguments['name']);
            }
            if (array_key_exists('url', $arguments)) {
                $data['url'] = $arguments['url'] ?: null;
            }
            if (array_key_exists('entity_type_id', $arguments)) {
                $data['entity_type_id'] = $arguments['entity_type_id'] ?: null;
            }
            if (array_key_exists('latitude', $arguments)) {
                $data['latitude'] = $arguments['latitude'];
            }
            if (array_key_exists('longitude', $arguments)) {
                $data['longitude'] = $arguments['longitude'];
            }
            if (array_key_exists('notes', $arguments)) {
                $data['notes'] = $arguments['notes'] ?: null;
            }

            $card->update($data);
            $card->load('slot:id,name', 'entityType:id,name');

            return ToolResult::success([
                'id' => $card->id,
                'name' => $card->name,
                'slot' => $card->slot?->name,
                'entity_type' => $card->entityType?->name,
                'message' => 'Card aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'pipeline', 'cards', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
