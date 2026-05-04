<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Syltjunkie\Models\SjPageSnapshot;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class ListPageSnapshotsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.page_snapshots.GET';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/page_snapshots - Listet Page-Snapshots (On-Page Daten: Title, Description, Headings, Score). Filter nach entity_url_id, Datum-Range. Unterstützt filters/sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'entity_url_id', 'captured_at']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                    ],
                    'entity_url_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Entity-URL-ID.',
                    ],
                    'date_from' => [
                        'type' => 'string',
                        'description' => 'Optional: Startdatum (YYYY-MM-DD).',
                    ],
                    'date_to' => [
                        'type' => 'string',
                        'description' => 'Optional: Enddatum (YYYY-MM-DD).',
                    ],
                    'include_raw_response' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Rohes API-Response mitliefern. Default: false.',
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

            $q = SjPageSnapshot::query()
                ->where('team_id', $rootTeamId)
                ->with('entityUrl:id,url,platform,entity_id', 'entityUrl.entity:id,name');

            if (!empty($arguments['entity_url_id'])) {
                $q->where('entity_url_id', (int) $arguments['entity_url_id']);
            }
            if (!empty($arguments['date_from'])) {
                $q->where('captured_at', '>=', $arguments['date_from']);
            }
            if (!empty($arguments['date_to'])) {
                $q->where('captured_at', '<=', $arguments['date_to']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'entity_url_id', 'captured_at', 'created_at']);
            $this->applyStandardSort($q, $arguments, ['captured_at', 'created_at', 'onpage_score', 'word_count', 'load_time'], 'captured_at', 'desc');
            $result = $this->applyStandardPaginationResult($q, $arguments);

            $includeRaw = !empty($arguments['include_raw_response']);

            $items = $result['data']->map(function ($snap) use ($includeRaw) {
                $item = [
                    'id' => $snap->id,
                    'uuid' => $snap->uuid,
                    'entity_url_id' => $snap->entity_url_id,
                    'url' => $snap->entityUrl?->url,
                    'entity_name' => $snap->entityUrl?->entity?->name,
                    'captured_at' => $snap->captured_at->toDateString(),
                    'status_code' => $snap->status_code,
                    'title' => $snap->title,
                    'meta_description' => $snap->meta_description,
                    'headings' => $snap->headings,
                    'word_count' => $snap->word_count,
                    'content_length' => $snap->content_length,
                    'internal_links_count' => $snap->internal_links_count,
                    'external_links_count' => $snap->external_links_count,
                    'image_count' => $snap->image_count,
                    'load_time' => $snap->load_time,
                    'onpage_score' => $snap->onpage_score,
                    'content_hash' => $snap->content_hash,
                    'created_at' => $snap->created_at?->toIso8601String(),
                ];
                if ($includeRaw) {
                    $item['raw_response'] = $snap->raw_response;
                }
                return $item;
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
            'tags' => ['syltjunkie', 'page_snapshots', 'on_page', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
