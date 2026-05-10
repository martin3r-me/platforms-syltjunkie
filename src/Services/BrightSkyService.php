<?php

namespace Platform\Syltjunkie\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrightSkyService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('syltjunkie.weather.api_base_url', 'https://api.brightsky.dev');
    }

    public function getCurrentWeather(float $lat, float $lon): ?array
    {
        return $this->request('/current_weather', [
            'lat' => $lat,
            'lon' => $lon,
        ]);
    }

    public function getWeather(float $lat, float $lon, string $date, string $lastDate): ?array
    {
        return $this->request('/weather', [
            'lat' => $lat,
            'lon' => $lon,
            'date' => $date,
            'last_date' => $lastDate,
        ]);
    }

    public function aggregateToDaily(array $hourlyRecords): array
    {
        $temperatures = array_filter(array_column($hourlyRecords, 'temperature'), fn ($v) => $v !== null);
        $precipitations = array_filter(array_column($hourlyRecords, 'precipitation'), fn ($v) => $v !== null);
        $windSpeeds = array_filter(array_column($hourlyRecords, 'wind_speed'), fn ($v) => $v !== null);
        $windGusts = array_filter(array_column($hourlyRecords, 'wind_gust_speed'), fn ($v) => $v !== null);
        $windDirections = array_filter(array_column($hourlyRecords, 'wind_direction'), fn ($v) => $v !== null);
        $cloudCovers = array_filter(array_column($hourlyRecords, 'cloud_cover'), fn ($v) => $v !== null);
        $pressures = array_filter(array_column($hourlyRecords, 'pressure_msl'), fn ($v) => $v !== null);
        $sunshineValues = array_filter(array_column($hourlyRecords, 'sunshine'), fn ($v) => $v !== null);
        $visibilities = array_filter(array_column($hourlyRecords, 'visibility'), fn ($v) => $v !== null);
        $humidities = array_filter(array_column($hourlyRecords, 'relative_humidity'), fn ($v) => $v !== null);
        $conditions = array_filter(array_column($hourlyRecords, 'condition'), fn ($v) => $v !== null);
        $icons = array_filter(array_column($hourlyRecords, 'icon'), fn ($v) => $v !== null);

        return [
            'temperature_min' => !empty($temperatures) ? round(min($temperatures), 1) : null,
            'temperature_max' => !empty($temperatures) ? round(max($temperatures), 1) : null,
            'temperature_avg' => !empty($temperatures) ? round(array_sum($temperatures) / count($temperatures), 1) : null,
            'precipitation_mm' => !empty($precipitations) ? round(array_sum($precipitations), 1) : null,
            'wind_speed_avg' => !empty($windSpeeds) ? round(array_sum($windSpeeds) / count($windSpeeds), 1) : null,
            'wind_gust_max' => !empty($windGusts) ? round(max($windGusts), 1) : null,
            'wind_direction' => !empty($windDirections) ? (int) round(array_sum($windDirections) / count($windDirections)) : null,
            'cloud_cover_avg' => !empty($cloudCovers) ? (int) round(array_sum($cloudCovers) / count($cloudCovers)) : null,
            'pressure_msl' => !empty($pressures) ? round(array_sum($pressures) / count($pressures), 1) : null,
            'sunshine_hours' => !empty($sunshineValues) ? round(array_sum($sunshineValues) / 60, 1) : null,
            'visibility_avg' => !empty($visibilities) ? (int) round(array_sum($visibilities) / count($visibilities)) : null,
            'relative_humidity_avg' => !empty($humidities) ? (int) round(array_sum($humidities) / count($humidities)) : null,
            'condition' => $this->dominantValue($conditions),
            'icon' => $this->dominantValue($icons),
        ];
    }

    protected function dominantValue(array $values): ?string
    {
        if (empty($values)) {
            return null;
        }

        $counts = array_count_values($values);
        arsort($counts);

        return array_key_first($counts);
    }

    protected function request(string $endpoint, array $params): ?array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout(15)
                ->retry(2, 500)
                ->get($endpoint, $params);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('BrightSky API error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('BrightSky API exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
