<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Syltjunkie\Models\SjEntityEvent;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class ListEntityEventsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.entity_events.GET';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/entity_events - Listet Events von Entities (z.B. Surf Cup Termine). Filter nach entity_id, upcoming_only. Unterstützt limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'entity_id', 'status']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                    ],
                    'entity_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Entity-ID.',
                    ],
                    'upcoming_only' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Nur zukünftige Events. Default: true.',
                        'default' => true,
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Status.',
                        'enum' => ['scheduled', 'cancelled', 'postponed', 'completed'],
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $q = SjEntityEvent::query()->where('team_id', $rootTeamId);

            if (!empty($arguments['entity_id'])) {
                $q->where('entity_id', (int) $arguments['entity_id']);
            }

            $upcomingOnly = $arguments['upcoming_only'] ?? true;
            if ($upcomingOnly) {
                $q->where('starts_at', '>=', now());
            }

            if (!empty($arguments['status'])) {
                $q->where('status', $arguments['status']);
            }

            $q->with('entity:id,name,slug');

            $this->applyStandardFilters($q, $arguments, ['team_id', 'entity_id', 'status']);
            $this->applyStandardSearch($q, $arguments, ['title', 'description', 'location_detail']);
            $this->applyStandardSort($q, $arguments, ['starts_at', 'created_at', 'title'], 'starts_at', 'asc');
            $result = $this->applyStandardPaginationResult($q, $arguments);

            $items = $result['data']->map(fn ($e) => [
                'id' => $e->id,
                'uuid' => $e->uuid,
                'entity_id' => $e->entity_id,
                'entity_name' => $e->entity?->name,
                'entity_slug' => $e->entity?->slug,
                'title' => $e->title,
                'description' => $e->description,
                'starts_at' => $e->starts_at->toIso8601String(),
                'ends_at' => $e->ends_at?->toIso8601String(),
                'is_all_day' => $e->is_all_day,
                'location_detail' => $e->location_detail,
                'status' => $e->status,
                'metadata' => $e->metadata,
            ])->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['syltjunkie', 'entity_events', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
