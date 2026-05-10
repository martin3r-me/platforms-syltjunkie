<?php

namespace Platform\Syltjunkie\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Platform\Core\Models\Team;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityType;
use Platform\Syltjunkie\Models\SjWeather;
use Platform\Syltjunkie\Services\BrightSkyService;

class FetchWeather extends Command
{
    protected $signature = 'syltjunkie:fetch-weather
                            {--entity-id= : Wetter nur für eine Entity}
                            {--days=7 : Forecast-Tage}
                            {--dry-run : Nur anzeigen, nicht speichern}';

    protected $description = 'Fetch weather data from Bright Sky (DWD) for all Ort entities';

    public function handle(BrightSkyService $brightSky): int
    {
        if (!config('syltjunkie.weather.enabled', true)) {
            $this->warn('Weather fetching is disabled (SYLTJUNKIE_WEATHER_ENABLED=false).');
            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run');
        $days = (int) $this->option('days');
        $entityId = $this->option('entity-id');
        $delayMs = config('syltjunkie.weather.request_delay_ms', 1000);

        $this->info('Syltjunkie Weather Fetch');
        $this->info('========================');
        if ($dryRun) {
            $this->warn('DRY-RUN Modus — keine Daten werden gespeichert');
        }
        $this->newLine();

        $teams = $this->resolveTeams();
        if ($teams->isEmpty()) {
            $this->warn('Keine Teams mit aktiven Syltjunkie-Entities gefunden.');
            return self::SUCCESS;
        }

        $totalFetched = 0;
        $totalErrors = 0;

        foreach ($teams as $team) {
            $this->info("Team: {$team->name} (ID: {$team->id})");

            $entities = $this->loadEntities($team->id, $entityId);

            if ($entities->isEmpty()) {
                $this->warn('  Keine Ort-Entities mit Koordinaten gefunden.');
                continue;
            }

            $this->info("  {$entities->count()} Entities gefunden");

            foreach ($entities as $entity) {
                $this->line("  → {$entity->name} ({$entity->latitude}, {$entity->longitude})");

                if ($dryRun) {
                    $this->info("    [dry-run] Würde Wetter abrufen für {$days}+1 Tage");
                    continue;
                }

                $today = Carbon::today()->format('Y-m-d');
                $lastDate = Carbon::today()->addDays($days)->format('Y-m-d');

                $response = $brightSky->getWeather(
                    $entity->latitude,
                    $entity->longitude,
                    $today,
                    $lastDate,
                );

                if (!$response || empty($response['weather'])) {
                    $this->error("    ✗ Keine Daten erhalten");
                    $totalErrors++;
                    usleep($delayMs * 1000);
                    continue;
                }

                $stationId = $response['sources'][0]['dwd_station_id'] ?? null;

                // Group hourly records by date
                $byDate = [];
                foreach ($response['weather'] as $record) {
                    $date = Carbon::parse($record['timestamp'])->format('Y-m-d');
                    $byDate[$date][] = $record;
                }

                $upserted = 0;
                foreach ($byDate as $date => $hourlyRecords) {
                    $aggregated = $brightSky->aggregateToDaily($hourlyRecords);
                    $recordType = ($date === $today) ? 'current' : 'forecast';

                    SjWeather::upsert([
                        array_merge($aggregated, [
                            'team_id' => $team->id,
                            'entity_id' => $entity->id,
                            'date' => $date,
                            'record_type' => $recordType,
                            'hourly_data' => json_encode($hourlyRecords),
                            'dwd_station_id' => $stationId,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]),
                    ], ['entity_id', 'date', 'record_type'], [
                        'team_id', 'temperature_min', 'temperature_max', 'temperature_avg',
                        'precipitation_mm', 'wind_speed_avg', 'wind_gust_max', 'wind_direction',
                        'cloud_cover_avg', 'pressure_msl', 'sunshine_hours',
                        'visibility_avg', 'relative_humidity_avg',
                        'condition', 'icon', 'hourly_data', 'dwd_station_id', 'updated_at',
                    ]);

                    $upserted++;
                }

                $this->info("    ✓ {$upserted} Tage gespeichert");
                $totalFetched++;

                usleep($delayMs * 1000);
            }
        }

        // Cleanup old forecasts
        if (!$dryRun) {
            $deleted = SjWeather::where('record_type', 'forecast')
                ->where('date', '<', today())
                ->delete();

            if ($deleted > 0) {
                $this->info("Alte Forecasts aufgeräumt: {$deleted} Records gelöscht");
            }

            // Cleanup old historical data beyond retention
            $retentionDays = config('syltjunkie.weather.retention_days', 365);
            $deletedOld = SjWeather::where('record_type', 'current')
                ->where('date', '<', today()->subDays($retentionDays))
                ->delete();

            if ($deletedOld > 0) {
                $this->info("Historische Daten aufgeräumt: {$deletedOld} Records gelöscht (>{$retentionDays} Tage)");
            }
        }

        $this->newLine();
        $this->info("Fertig: {$totalFetched} Entities abgerufen, {$totalErrors} Fehler");

        return $totalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function loadEntities(int $teamId, ?string $entityId)
    {
        $query = SjEntity::where('team_id', $teamId)
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereHas('entityType', fn ($q) => $q->where('code', 'ort'));

        if ($entityId) {
            $query->where('id', $entityId);
        }

        return $query->get();
    }

    protected function resolveTeams()
    {
        return Team::whereHas('users')
            ->whereIn('id', function ($q) {
                $q->select('team_id')
                    ->from('sj_entities')
                    ->where('is_active', true)
                    ->distinct();
            })
            ->get();
    }
}
