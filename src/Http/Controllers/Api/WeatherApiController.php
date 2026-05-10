<?php

namespace Platform\Syltjunkie\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Platform\Core\Http\Controllers\ApiController;
use Platform\Syltjunkie\Http\Controllers\Api\Concerns\ResolvesPublicTeam;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjWeather;

class WeatherApiController extends ApiController
{
    use ResolvesPublicTeam;

    public function index(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        $entities = SjEntity::where('team_id', $teamId)
            ->where('is_active', true)
            ->whereHas('entityType', fn ($q) => $q->where('code', 'ort'))
            ->with(['weather' => fn ($q) => $q
                ->where(function ($sub) {
                    $sub->where(function ($w) {
                        $w->where('record_type', 'current')
                            ->where('date', today());
                    })->orWhere(function ($w) {
                        $w->where('record_type', 'forecast')
                            ->where('date', '>=', today());
                    });
                })
                ->orderBy('date'),
            ])
            ->get();

        $data = $entities->map(function (SjEntity $entity) {
            $current = $entity->weather
                ->where('record_type', 'current')
                ->where('date', today()->toDateString())
                ->first();

            $forecast = $entity->weather
                ->where('record_type', 'forecast')
                ->values();

            return [
                'entity' => [
                    'id' => $entity->id,
                    'name' => $entity->name,
                    'slug' => $entity->slug,
                ],
                'current' => $current ? $this->formatWeatherRecord($current) : null,
                'forecast' => $forecast->map(fn ($r) => $this->formatWeatherRecord($r))->values(),
            ];
        });

        return $this->success($data);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        $entity = SjEntity::where('team_id', $teamId)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$entity) {
            return $this->notFound('Entity not found.');
        }

        $current = SjWeather::forEntity($entity->id)
            ->current()
            ->where('date', today())
            ->first();

        $forecast = SjWeather::forEntity($entity->id)
            ->forecast()
            ->orderBy('date')
            ->get();

        $includeDetail = $request->boolean('detail');

        $data = [
            'entity' => [
                'id' => $entity->id,
                'name' => $entity->name,
                'slug' => $entity->slug,
            ],
            'current' => $current ? $this->formatWeatherRecord($current, $includeDetail) : null,
            'forecast' => $forecast->map(fn ($r) => $this->formatWeatherRecord($r, $includeDetail))->values(),
        ];

        return $this->success($data);
    }

    protected function formatWeatherRecord(SjWeather $record, bool $includeHourly = false): array
    {
        $data = [
            'date' => $record->date->format('Y-m-d'),
            'temperature_min' => $record->temperature_min,
            'temperature_max' => $record->temperature_max,
            'temperature_avg' => $record->temperature_avg,
            'precipitation_mm' => $record->precipitation_mm,
            'wind_speed_avg' => $record->wind_speed_avg,
            'wind_gust_max' => $record->wind_gust_max,
            'wind_direction' => $record->wind_direction,
            'cloud_cover_avg' => $record->cloud_cover_avg,
            'pressure_msl' => $record->pressure_msl,
            'sunshine_hours' => $record->sunshine_hours,
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
