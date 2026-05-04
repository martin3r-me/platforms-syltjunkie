<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Syltjunkie\Models\SjPageChange;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class ListPageChangesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.page_changes.GET';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/page_changes - Listet erkannte Seitenänderungen (Title, H1, H2, Word Count, Status Code, etc.). '
            . 'Filter nach entity_id, entity_url_id, severity, Datum-Range. Unterstützt filters/sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'entity_url_id', 'detected_at', 'change_type', 'severity']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                    ],
                    'entity_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Entity-ID (alle URLs dieser Entity).',
                    ],
                    'entity_url_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Entity-URL-ID.',
                    ],
                    'severity' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Severity.',
                        'enum' => ['minor', 'moderate', 'major'],
                    ],
                    'change_type' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Change-Type (title, meta_description, h1, h2_added, h2_removed, word_count, status_code, load_time, onpage_score).',
                    ],
                    'date_from' => [
                        'type' => 'string',
                        'description' => 'Optional: Startdatum (YYYY-MM-DD).',
                    ],
                    'date_to' => [
                        'type' => 'string',
                        'description' => 'Optional: Enddatum (YYYY-MM-DD).',
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

            $q = SjPageChange::query()
                ->where('team_id', $rootTeamId)
                ->with('entityUrl:id,url,platform,entity_id', 'entityUrl.entity:id,name');

            if (!empty($arguments['entity_url_id'])) {
                $q->where('entity_url_id', (int) $arguments['entity_url_id']);
            }
            if (!empty($arguments['entity_id'])) {
                $q->whereHas('entityUrl', fn($sub) => $sub->where('entity_id', (int) $arguments['entity_id']));
            }
            if (!empty($arguments['severity'])) {
                $q->where('severity', $arguments['severity']);
            }
            if (!empty($arguments['change_type'])) {
                $q->where('change_type', $arguments['change_type']);
            }
            if (!empty($arguments['date_from'])) {
                $q->where('detected_at', '>=', $arguments['date_from']);
            }
            if (!empty($arguments['date_to'])) {
                $q->where('detected_at', '<=', $arguments['date_to']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'entity_url_id', 'detected_at', 'change_type', 'severity', 'created_at']);
            $this->applyStandardSort($q, $arguments, ['detected_at', 'created_at', 'severity', 'change_type'], 'detected_at', 'desc');
            $result = $this->applyStandardPaginationResult($q, $arguments);

            $items = $result['data']->map(function ($change) {
                return [
                    'id' => $change->id,
                    'uuid' => $change->uuid,
                    'entity_url_id' => $change->entity_url_id,
                    'url' => $change->entityUrl?->url,
                    'entity_name' => $change->entityUrl?->entity?->name,
                    'detected_at' => $change->detected_at->toDateString(),
                    'change_type' => $change->change_type,
                    'severity' => $change->severity,
                    'old_value' => $change->old_value,
                    'new_value' => $change->new_value,
                    'delta' => $change->delta,
                    'context' => $change->context,
                    'created_at' => $change->created_at?->toIso8601String(),
                ];
            })->toArray();

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
            'tags' => ['syltjunkie', 'page_changes', 'change_detection', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
