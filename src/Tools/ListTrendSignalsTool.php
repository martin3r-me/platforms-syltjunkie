<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjTrendSignal;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class ListTrendSignalsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.trend_signals.GET';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/trend-signals - Listet Trend Signals mit optionalen Filtern.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'entity_id' => ['type' => 'integer', 'description' => 'Optional: Filter nach Entity.'],
                'signal_type' => ['type' => 'string', 'description' => 'Optional: new_keyword|volume_spike|rating_drop|review_velocity|ranking_change.'],
                'severity' => ['type' => 'string', 'enum' => ['info', 'watch', 'action'], 'description' => 'Optional: Filter nach Severity.'],
                'status' => ['type' => 'string', 'enum' => ['new', 'acknowledged', 'resolved'], 'description' => 'Optional: Filter nach Status.'],
                'limit' => ['type' => 'integer', 'description' => 'Max Ergebnisse. Default 50.'],
                'offset' => ['type' => 'integer', 'description' => 'Offset für Pagination. Default 0.'],
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
            $rootTeamId = (int) $resolved['root_team_id'];

            $limit = min((int) ($arguments['limit'] ?? 50), 200);
            $offset = (int) ($arguments['offset'] ?? 0);

            $query = SjTrendSignal::where('team_id', $rootTeamId)
                ->with(['entity:id,name', 'keyword:id,keyword'])
                ->orderByDesc('detected_at')
                ->orderByDesc('id');

            if (!empty($arguments['entity_id'])) {
                $query->where('entity_id', $arguments['entity_id']);
            }
            if (!empty($arguments['signal_type'])) {
                $query->where('signal_type', $arguments['signal_type']);
            }
            if (!empty($arguments['severity'])) {
                $query->where('severity', $arguments['severity']);
            }
            if (!empty($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }

            $total = $query->count();
            $signals = $query->offset($offset)->limit($limit)->get();

            return ToolResult::success([
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'signals' => $signals->map(fn($s) => [
                    'id' => $s->id,
                    'uuid' => $s->uuid,
                    'signal_type' => $s->signal_type,
                    'severity' => $s->severity,
                    'status' => $s->status,
                    'title' => $s->title,
                    'description' => $s->description,
                    'entity' => $s->entity ? ['id' => $s->entity->id, 'name' => $s->entity->name] : null,
                    'keyword' => $s->keyword ? ['id' => $s->keyword->id, 'keyword' => $s->keyword->keyword] : null,
                    'metric_before' => $s->metric_before,
                    'metric_after' => $s->metric_after,
                    'metric_delta' => $s->metric_delta,
                    'detected_at' => $s->detected_at->toDateString(),
                ])->toArray(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['syltjunkie', 'trends', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'read',
            'idempotent' => true,
        ];
    }
}
