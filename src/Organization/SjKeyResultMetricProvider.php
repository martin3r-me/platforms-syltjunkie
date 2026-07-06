<?php

namespace Platform\Syltjunkie\Organization;

use Illuminate\Support\Facades\Schema;
use Platform\Core\Contracts\KeyResultMetricProvider;
use Platform\Core\KeyResult\MetricValue;
use Platform\Syltjunkie\Models\SjContentPiece;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityScore;
use Platform\Syltjunkie\Models\SjInstagramAccountInsight;

/**
 * KR-Metriken aus dem SYLTJUNKIE-Modul. binding=team (kein Selector): die Metriken
 * messen den Zustand des Venture-Datenbestands im Team-Scope.
 *
 * Provider ist dumm: liefert Rohwerte. Zielerreichung macht die OKR-Engine.
 */
class SjKeyResultMetricProvider implements KeyResultMetricProvider
{
    public function metricDefinitions(): array
    {
        $roles = ['score', 'gate', 'cap', 'info'];

        return [
            ['metric_key' => 'syltjunkie.entities_typed_count', 'module' => 'syltjunkie',
             'label' => 'Typisierte Entitäten im Graph', 'value_type' => 'count', 'unit' => null,
             'default_polarity' => 'up', 'supported_roles' => $roles, 'binding' => 'team',
             'selector_schema' => [], 'supports_window' => false],

            ['metric_key' => 'syltjunkie.content_pieces_published_count', 'module' => 'syltjunkie',
             'label' => 'Content-Pieces publiziert', 'value_type' => 'count', 'unit' => null,
             'default_polarity' => 'up', 'supported_roles' => $roles, 'binding' => 'team',
             'selector_schema' => [], 'supports_window' => false],

            ['metric_key' => 'syltjunkie.instagram_followers', 'module' => 'syltjunkie',
             'label' => 'Instagram-Follower', 'value_type' => 'count', 'unit' => null,
             'default_polarity' => 'up', 'supported_roles' => $roles, 'binding' => 'team',
             'selector_schema' => [], 'supports_window' => false],

            ['metric_key' => 'syltjunkie.avg_visibility_score', 'module' => 'syltjunkie',
             'label' => 'Ø Visibility-Score (SjEntityScore)', 'value_type' => 'number', 'unit' => null,
             'default_polarity' => 'up', 'supported_roles' => $roles, 'binding' => 'team',
             'selector_schema' => [], 'supports_window' => false],
        ];
    }

    public function resolveBatch(string $metricKey, array $requests): array
    {
        $out = [];
        foreach ($requests as $key => $req) {
            $out[$key] = $this->resolveOne($metricKey, $req->scope['team_ids'] ?? []);
        }

        return $out;
    }

    protected function resolveOne(string $metricKey, array $teamIds): MetricValue
    {
        switch ($metricKey) {
            case 'syltjunkie.entities_typed_count':
                $n = $this->scoped(SjEntity::class, $teamIds)
                    ->whereNotNull('entity_type_id')
                    ->where('is_active', true)
                    ->count();
                return MetricValue::of((float) $n, ['count' => $n], "$n typisierte Entitäten");

            case 'syltjunkie.content_pieces_published_count':
                $n = $this->scoped(SjContentPiece::class, $teamIds)
                    ->where('status', 'published')
                    ->count();
                return MetricValue::of((float) $n, ['count' => $n], "$n publiziert");

            case 'syltjunkie.instagram_followers':
                $row = $this->scoped(SjInstagramAccountInsight::class, $teamIds)
                    ->latest('id')
                    ->first();
                if (! $row) {
                    return MetricValue::unavailable('keine Instagram-Daten');
                }
                $f = (int) $row->current_followers;
                return MetricValue::of((float) $f, ['followers' => $f], "$f Follower");

            case 'syltjunkie.avg_visibility_score':
                $avg = $this->scoped(SjEntityScore::class, $teamIds)->avg('visibility_score');
                if ($avg === null) {
                    return MetricValue::unavailable('keine Scores');
                }
                return MetricValue::of((float) $avg, ['avg' => round((float) $avg, 2)], round((float) $avg, 1) . ' Ø');

            default:
                return MetricValue::unavailable('unknown metric');
        }
    }

    /** Query mit Team-Scope, falls die Tabelle eine team_id-Spalte hat. */
    protected function scoped(string $modelClass, array $teamIds): \Illuminate\Database\Eloquent\Builder
    {
        $query = $modelClass::query();
        $table = (new $modelClass())->getTable();

        if (! empty($teamIds) && Schema::hasColumn($table, 'team_id')) {
            $query->whereIn('team_id', $teamIds);
        }

        return $query;
    }
}
