<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityEvent;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class CreateEntityEventTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.entity_events.POST';
    }

    public function getDescription(): string
    {
        return 'POST /syltjunkie/entity_events - Erstellt ein Event für eine Entity (z.B. Surf Cup Termin). ERFORDERLICH: entity_id, starts_at.';
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
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Entity, zu der das Event gehört.',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Optional: Titel des Events (z.B. "Surf Cup Sylt - Tag 1"). Kann null sein wenn die Entity nur einen Termin hat.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung des Events.',
                ],
                'starts_at' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Beginn des Events (ISO 8601, z.B. "2026-07-15T10:00:00").',
                ],
                'ends_at' => [
                    'type' => 'string',
                    'description' => 'Optional: Ende des Events (ISO 8601). Nullable für ganztägig/offen.',
                ],
                'is_all_day' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Ganztägiges Event. Default: false.',
                    'default' => false,
                ],
                'location_detail' => [
                    'type' => 'string',
                    'description' => 'Optional: Detailort innerhalb der Entity-Location (z.B. "Brandenburger Strand").',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Optional: Status des Events. Default: scheduled.',
                    'enum' => ['scheduled', 'cancelled', 'postponed', 'completed'],
                    'default' => 'scheduled',
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Zusätzliche Daten als JSON (z.B. Ticketlink, Preise).',
                ],
            ],
            'required' => ['entity_id', 'starts_at'],
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

            if (empty($arguments['entity_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'entity_id ist erforderlich.');
            }
            if (empty($arguments['starts_at'])) {
                return ToolResult::error('VALIDATION_ERROR', 'starts_at ist erforderlich.');
            }

            $entity = SjEntity::where('team_id', $rootTeamId)->find((int) $arguments['entity_id']);
            if (!$entity) {
                return ToolResult::error('NOT_FOUND', 'Entity nicht gefunden.');
            }

            $event = SjEntityEvent::create([
                'team_id' => $rootTeamId,
                'entity_id' => $entity->id,
                'title' => ($arguments['title'] ?? null) ?: null,
                'description' => ($arguments['description'] ?? null) ?: null,
                'starts_at' => $arguments['starts_at'],
                'ends_at' => ($arguments['ends_at'] ?? null) ?: null,
                'is_all_day' => $arguments['is_all_day'] ?? false,
                'location_detail' => ($arguments['location_detail'] ?? null) ?: null,
                'status' => $arguments['status'] ?? 'scheduled',
                'metadata' => (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null,
            ]);

            return ToolResult::success([
                'id' => $event->id,
                'uuid' => $event->uuid,
                'entity_id' => $event->entity_id,
                'entity_name' => $entity->name,
                'title' => $event->title,
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'status' => $event->status,
                'message' => 'Event erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'entity_events', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
