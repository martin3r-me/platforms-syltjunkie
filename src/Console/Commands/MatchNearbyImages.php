<?php

namespace Platform\Syltjunkie\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjImage;

class MatchNearbyImages extends Command
{
    protected $signature = 'syltjunkie:match-nearby-images
                            {--entity-id= : Match images for a single entity}
                            {--max-entities=200 : Maximum number of entities to process}
                            {--dry-run : Preview matches without inserting}';

    protected $description = 'Match geo-tagged images to nearby entities based on coordinates';

    protected array $radiusMap = [
        'ort'             => 2.0,
        'strand'          => 0.3,
        'duene'           => 0.3,
        'restaurant'      => 0.1,
        'cafe'            => 0.1,
        'bar'             => 0.1,
        'bistro'          => 0.1,
        'imbiss'          => 0.1,
        'baeckerei'       => 0.1,
        'hotel'           => 0.15,
        'pension'         => 0.15,
        'ferienwohnung'   => 0.15,
        'ferienhaus'      => 0.15,
        'camping'         => 0.15,
        'festival'        => 0.5,
        'veranstaltung'   => 0.5,
        'markt'           => 0.5,
    ];

    protected float $defaultRadius = 0.2;

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $maxEntities = (int) $this->option('max-entities');
        $entityId = $this->option('entity-id');

        if ($isDryRun) {
            $this->info('DRY-RUN mode — no changes will be made.');
        }

        $query = SjEntity::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('is_active', true)
            ->with('entityType:id,code');

        if ($entityId) {
            $query->where('id', $entityId);
        }

        $entities = $query->limit($maxEntities)->get();

        $this->info("Processing {$entities->count()} entities...");

        $totalMatched = 0;

        foreach ($entities as $entity) {
            $matched = $this->matchEntity($entity, $isDryRun);
            $totalMatched += $matched;
        }

        $this->info("Done. Total new matches: {$totalMatched}");

        return self::SUCCESS;
    }

    protected function matchEntity(SjEntity $entity, bool $isDryRun): int
    {
        $typeCode = $entity->entityType?->code;
        $radius = $this->radiusMap[$typeCode] ?? $this->defaultRadius;

        $existingImageIds = DB::table('sj_image_entity')
            ->where('entity_id', $entity->id)
            ->pluck('sj_image_id');

        $nearbyImages = SjImage::nearby($entity->latitude, $entity->longitude, $radius)
            ->where('team_id', $entity->team_id)
            ->whereNotIn('id', $existingImageIds)
            ->limit(50)
            ->get();

        if ($nearbyImages->isEmpty()) {
            return 0;
        }

        if ($isDryRun) {
            $this->line("  [{$entity->name}] ({$typeCode}, r={$radius}km): {$nearbyImages->count()} new matches");
            foreach ($nearbyImages->take(5) as $img) {
                $dist = $this->haversineMeters($entity->latitude, $entity->longitude, $img->latitude, $img->longitude);
                $this->line("    - #{$img->id} \"{$img->title}\" — {$dist}m");
            }
            return $nearbyImages->count();
        }

        $rows = [];
        $now = now();
        $sortBase = 100;

        foreach ($nearbyImages as $i => $image) {
            $dist = $this->haversineMeters($entity->latitude, $entity->longitude, $image->latitude, $image->longitude);
            $rows[] = [
                'sj_image_id' => $image->id,
                'entity_id'   => $entity->id,
                'sort_order'  => $sortBase + $i,
                'is_primary'  => false,
                'source'      => 'geo_matched',
                'distance_m'  => $dist,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        DB::table('sj_image_entity')->insert($rows);

        $this->line("  [{$entity->name}] ({$typeCode}, r={$radius}km): {$nearbyImages->count()} matched");

        return $nearbyImages->count();
    }

    protected function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
           * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round($earthRadius * $c);
    }
}
