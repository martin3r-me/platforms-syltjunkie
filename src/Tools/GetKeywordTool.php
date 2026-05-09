<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjKeyword;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class GetKeywordTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.keywords.GET_ONE';
    }

    public function getDescription(): string
    {
        return 'GET /syltjunkie/keywords/{id} - Gibt ein einzelnes Keyword mit allen Details inkl. Trends-Daten, Content Pieces und Entities zurück.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'keyword_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Keyword-ID.'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
            ],
            'required' => ['keyword_id'],
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

            $keyword = SjKeyword::where('team_id', $rootTeamId)
                ->with([
                    'entities:id,name,ort',
                    'contentPieces:id,title,status,content_type',
                ])
                ->find($arguments['keyword_id'] ?? 0);

            if (!$keyword) {
                return ToolResult::error('NOT_FOUND', 'Keyword nicht gefunden.');
            }

            return ToolResult::success([
                'id' => $keyword->id,
                'uuid' => $keyword->uuid,
                'keyword' => $keyword->keyword,
                'search_volume' => $keyword->search_volume,
                'keyword_difficulty' => $keyword->keyword_difficulty,
                'cpc_euro' => $keyword->cpc_euro,
                'competition' => $keyword->competition,
                'search_intent' => $keyword->search_intent,
                'topic' => $keyword->topic,
                'monthly_volumes' => $keyword->monthly_volumes,
                'peak_month' => $keyword->peak_month,
                'seasonality_index' => $keyword->seasonality_index,
                'trends_average_interest' => $keyword->trends_average_interest,
                'trends_peak_interest' => $keyword->trends_peak_interest,
                'trends_sparkline' => $keyword->trends_sparkline,
                'trends_fetched_at' => $keyword->trends_fetched_at?->toDateString(),
                'last_fetched_at' => $keyword->last_fetched_at?->toDateString(),
                'entities' => $keyword->entities->map(fn($e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'ort' => $e->ort,
                    'attribution_type' => $e->pivot->attribution_type,
                    'confidence' => $e->pivot->confidence,
                ])->toArray(),
                'content_pieces' => $keyword->contentPieces->map(fn($c) => [
                    'id' => $c->id,
                    'title' => $c->title,
                    'status' => $c->status,
                    'content_type' => $c->content_type,
                    'is_primary' => (bool) $c->pivot->is_primary,
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
            'tags' => ['syltjunkie', 'keywords', 'detail'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'read',
            'idempotent' => true,
        ];
    }
}
