<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Syltjunkie\Models\SjUrlSnapshot;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class ListUrlSnapshotsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.url_snapshots.GET';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/url_snapshots - Listet URL-Snapshots (Keywords, Rankings, Traffic). Filter nach entity_url_id, Datum-Range. Unterstützt filters/sort/limit/offset.';
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
                    'include_keywords' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Keywords-Array mitliefern. Default: false (nur Count).',
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

            $q = SjUrlSnapshot::query()
                ->where('team_id', $rootTeamId)
                ->with('entityUrl:id,url,platform,entity_id');

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
            $this->applyStandardSort($q, $arguments, ['captured_at', 'created_at', 'organic_traffic_estimate', 'domain_authority'], 'captured_at', 'desc');
            $result = $this->applyStandardPaginationResult($q, $arguments);

            $includeKeywords = !empty($arguments['include_keywords']);
            $includeRaw = !empty($arguments['include_raw_response']);

            $items = $result['data']->map(function ($snap) use ($includeKeywords, $includeRaw) {
                $item = [
                    'id' => $snap->id,
                    'uuid' => $snap->uuid,
                    'entity_url_id' => $snap->entity_url_id,
                    'url' => $snap->entityUrl?->url,
                    'platform' => $snap->entityUrl?->platform,
                    'captured_at' => $snap->captured_at->toDateString(),
                    'keywords_count' => is_array($snap->keywords) ? count($snap->keywords) : 0,
                    'organic_traffic_estimate' => $snap->organic_traffic_estimate,
                    'domain_authority' => $snap->domain_authority,
                    'backlinks_count' => $snap->backlinks_count,
                    'created_at' => $snap->created_at?->toIso8601String(),
                ];
                if ($includeKeywords) {
                    $item['keywords'] = $snap->keywords;
                }
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
            'tags' => ['syltjunkie', 'url_snapshots', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
