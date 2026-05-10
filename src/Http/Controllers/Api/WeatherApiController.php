<?php

namespace Platform\Syltjunkie\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Platform\Core\Http\Controllers\ApiController;
use Platform\Syltjunkie\Http\Controllers\Api\Concerns\ResolvesPublicTeam;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjWeather;

class WeatherApiController extends ApiController
{
    use ResolvesPublicTeam;

    /**
     * GET /weather — Alle Orte mit aktuellem Wetter + Forecast
     *
     * Für die Übersichtsseite / Karten-Widget im Frontend.
     */
    public function index(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);
        $today = Carbon::today();

        $entities = SjEntity::where('team_id', $teamId)
            ->where('is_active', true)
            ->whereHas('entityType', fn ($q) => $q->where('code', 'ort'))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('name')
            ->get();

        $entityIds = $entities->pluck('id');

        // Load all relevant weather records in one query
        $weatherRecords = SjWeather::whereIn('entity_id', $entityIds)
            ->where(function ($q) use ($today) {
                $q->where(function ($w) use ($today) {
                    $w->where('record_type', 'current')->where('date', $today);
                })->orWhere(function ($w) use ($today) {
                    $w->where('record_type', 'forecast')->where('date', '>=', $today);
                });
            })
            ->orderBy('date')
            ->get()
            ->groupBy('entity_id');

        $data = $entities->map(function (SjEntity $entity) use ($weatherRecords, $today) {
            $records = $weatherRecords->get($entity->id, collect());

            $current = $records->firstWhere('record_type', 'current');
            $forecast = $records->where('record_type', 'forecast')->values();

            return [
                'entity' => [
                    'id' => $entity->id,
                    'name' => $entity->name,
                    'slug' => $entity->slug,
                    'lat' => $entity->latitude,
                    'lng' => $entity->longitude,
                ],
                'current' => $current ? $this->formatWeatherRecord($current) : null,
                'forecast' => $forecast->map(fn ($r) => $this->formatWeatherRecord($r))->values(),
            ];
        });

        return $this->success($data);
    }

    /**
     * GET /weather/{slug} — Wetter für einen bestimmten Ort
     *
     * Für das Orts-Widget im Frontend.
     * Optional: ?detail=true für stündliche Daten.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);
        $today = Carbon::today();

        $entity = SjEntity::where('team_id', $teamId)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$entity) {
            return $this->notFound('Entity not found.');
        }

        $current = SjWeather::forEntity($entity->id)
            ->current()
            ->where('date', $today)
            ->first();

        $forecast = SjWeather::forEntity($entity->id)
            ->forecast()
            ->orderBy('date')
            ->get();

        $includeHourly = $request->boolean('detail');

        $data = [
            'entity' => [
                'id' => $entity->id,
                'name' => $entity->name,
                'slug' => $entity->slug,
                'lat' => $entity->latitude,
                'lng' => $entity->longitude,
            ],
            'current' => $current ? $this->formatWeatherRecord($current, $includeHourly) : null,
            'forecast' => $forecast->map(fn ($r) => $this->formatWeatherRecord($r, $includeHourly))->values(),
            'updated_at' => $current?->updated_at?->toIso8601String()
                ?? $forecast->first()?->updated_at?->toIso8601String(),
        ];

        return $this->success($data);
    }

    protected function formatWeatherRecord(SjWeather $record, bool $includeHourly = false): array
    {
        $data = [
            'date' => $record->date->format('Y-m-d'),
            'weekday' => $record->date->locale('de')->isoFormat('dd'),
            'temperature_min' => $record->temperature_min !== null ? (float) $record->temperature_min : null,
            'temperature_max' => $record->temperature_max !== null ? (float) $record->temperature_max : null,
            'temperature_avg' => $record->temperature_avg !== null ? (float) $record->temperature_avg : null,
            'precipitation_mm' => $record->precipitation_mm !== null ? (float) $record->precipitation_mm : null,
            'wind_speed_avg' => $record->wind_speed_avg !== null ? (float) $record->wind_speed_avg : null,
            'wind_gust_max' => $record->wind_gust_max !== null ? (float) $record->wind_gust_max : null,
            'wind_direction' => $record->wind_direction,
            'cloud_cover_avg' => $record->cloud_cover_avg,
            'pressure_msl' => $record->pressure_msl !== null ? (float) $record->pressure_msl : null,
            'sunshine_hours' => $record->sunshine_hours !== null ? (float) $record->sunshine_hours : null,
            'visibility_avg' => $record->visibility_avg,
            'relative_humidity_avg' => $record->relative_humidity_avg,
            'condition' => $record->condition,
            'icon' => $record->icon,
        ];

        if ($includeHourly) {
            $data['hourly_data'] = $record->hourly_data;
        }

        return $data;
    }
}
