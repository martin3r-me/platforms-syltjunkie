<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjKeyword;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class ListKeywordsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.keywords.GET';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/keywords - Listet Keywords mit optionalen Filtern (Suchbegriff, Intent, Topic, Volume).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'search' => ['type' => 'string', 'description' => 'Optional: Keyword-Suche (LIKE).'],
                'search_intent' => ['type' => 'string', 'enum' => ['informational', 'navigational', 'commercial', 'transactional'], 'description' => 'Optional: Filter nach Search Intent.'],
                'topic' => ['type' => 'string', 'description' => 'Optional: Filter nach Topic.'],
                'volume_min' => ['type' => 'integer', 'description' => 'Optional: Mindest-Suchvolumen.'],
                'volume_max' => ['type' => 'integer', 'description' => 'Optional: Maximal-Suchvolumen.'],
                'has_trends' => ['type' => 'boolean', 'description' => 'Optional: Nur Keywords mit/ohne Trends-Daten.'],
                'sort' => ['type' => 'string', 'enum' => ['keyword', 'search_volume', 'keyword_difficulty', 'cpc_cents', 'trends_average_interest'], 'description' => 'Sortierung. Default: search_volume.'],
                'sort_dir' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'description' => 'Sortierrichtung. Default: desc.'],
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
            $sortField = $arguments['sort'] ?? 'search_volume';
            $sortDir = ($arguments['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

            $query = SjKeyword::where('team_id', $rootTeamId);

            if (!empty($arguments['search'])) {
                $query->where('keyword', 'like', '%' . $arguments['search'] . '%');
            }
            if (!empty($arguments['search_intent'])) {
                $query->where('search_intent', $arguments['search_intent']);
            }
            if (!empty($arguments['topic'])) {
                $query->where('topic', $arguments['topic']);
            }
            if (isset($arguments['volume_min'])) {
                $query->where('search_volume', '>=', (int) $arguments['volume_min']);
            }
            if (isset($arguments['volume_max'])) {
                $query->where('search_volume', '<=', (int) $arguments['volume_max']);
            }
            if (isset($arguments['has_trends'])) {
                if ($arguments['has_trends']) {
                    $query->whereNotNull('trends_fetched_at');
                } else {
                    $query->whereNull('trends_fetched_at');
                }
            }

            $query->orderBy($sortField, $sortDir);

            $total = $query->count();
            $keywords = $query->offset($offset)->limit($limit)->get();

            return ToolResult::success([
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'keywords' => $keywords->map(fn($k) => [
                    'id' => $k->id,
                    'keyword' => $k->keyword,
                    'search_volume' => $k->search_volume,
                    'keyword_difficulty' => $k->keyword_difficulty,
                    'cpc_euro' => $k->cpc_euro,
                    'search_intent' => $k->search_intent,
                    'topic' => $k->topic,
                    'trends_average_interest' => $k->trends_average_interest,
                    'trends_peak_interest' => $k->trends_peak_interest,
                    'trends_fetched_at' => $k->trends_fetched_at?->toDateString(),
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
            'tags' => ['syltjunkie', 'keywords', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'read',
            'idempotent' => true,
        ];
    }
}
