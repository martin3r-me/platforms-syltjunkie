<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Syltjunkie\Models\SjEntityUrl;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class ListEntityUrlsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.entity_urls.GET';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/entity_urls - Listet Seed-URLs von Entities. Filter nach entity_id, platform, is_primary. Unterstützt filters/search/sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'entity_id', 'platform', 'is_primary', 'is_active']),
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
                    'platform' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Platform.',
                        'enum' => ['website', 'google_maps', 'tripadvisor', 'instagram', 'facebook', 'booking', 'yelp', 'other'],
                    ],
                    'is_primary' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Nur primäre URLs anzeigen.',
                    ],
                    'include_latest_snapshot' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Letzten Snapshot pro URL mitladen. Default: false.',
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

            $q = SjEntityUrl::query()
                ->where('team_id', $rootTeamId)
                ->where('is_active', true);

            if (!empty($arguments['entity_id'])) {
                $q->where('entity_id', (int) $arguments['entity_id']);
            }
            if (!empty($arguments['platform'])) {
                $q->where('platform', $arguments['platform']);
            }
            if (isset($arguments['is_primary'])) {
                $q->where('is_primary', (bool) $arguments['is_primary']);
            }

            $q->with('entity:id,name,slug');

            $includeSnapshot = !empty($arguments['include_latest_snapshot']);
            if ($includeSnapshot) {
                $q->with(['snapshots' => fn($sub) => $sub->orderByDesc('captured_at')->limit(1)]);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'entity_id', 'platform', 'is_primary', 'is_active', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['url']);
            $this->applyStandardSort($q, $arguments, ['url', 'platform', 'created_at'], 'created_at', 'desc');
            $result = $this->applyStandardPaginationResult($q, $arguments);

            $items = $result['data']->map(function ($eu) use ($includeSnapshot) {
                $item = [
                    'id' => $eu->id,
                    'uuid' => $eu->uuid,
                    'entity_id' => $eu->entity_id,
                    'entity_name' => $eu->entity?->name,
                    'url' => $eu->url,
                    'platform' => $eu->platform,
                    'is_primary' => $eu->is_primary,
                    'is_active' => $eu->is_active,
                    'last_checked_at' => $eu->last_checked_at?->toIso8601String(),
                    'created_at' => $eu->created_at?->toIso8601String(),
                ];
                if ($includeSnapshot && $eu->snapshots->isNotEmpty()) {
                    $snap = $eu->snapshots->first();
                    $item['latest_snapshot'] = [
                        'id' => $snap->id,
                        'captured_at' => $snap->captured_at->toDateString(),
                        'keywords_count' => is_array($snap->keywords) ? count($snap->keywords) : 0,
                        'organic_traffic_estimate' => $snap->organic_traffic_estimate,
                        'domain_authority' => $snap->domain_authority,
                        'backlinks_count' => $snap->backlinks_count,
                    ];
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
            'tags' => ['syltjunkie', 'entity_urls', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
