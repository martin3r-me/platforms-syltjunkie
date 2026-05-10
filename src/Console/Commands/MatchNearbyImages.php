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

        if ($entityId) {
            $entities = SjEntity::where('is_active', true)
                ->where('id', $entityId)
                ->where(function ($q) {
                    $q->whereNotNull('latitude')->whereNotNull('longitude');
                    $q->orWhereRaw('geometry IS NOT NULL');
                })
                ->with('entityType:id,code')
                ->get();
        } else {
            $entities = $this->loadPrioritizedEntities($maxEntities);
        }

        $this->info("Processing {$entities->count()} entities...");

        $totalMatched = 0;

        foreach ($entities as $entity) {
            $matched = $this->matchEntity($entity, $isDryRun);
            $totalMatched += $matched;
        }

        $this->info("Done. Total new matches: {$totalMatched}");

        return self::SUCCESS;
    }

    /**
     * Load entities in priority order:
     * 1. Never geo-matched (no pivot rows with source=geo_matched)
     * 2. Have new images in radius since last match
     * 3. Oldest last-matched first (rotating)
     */
    protected function loadPrioritizedEntities(int $limit): \Illuminate\Support\Collection
    {
        $baseConditions = fn ($q) => $q->where('is_active', true)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('latitude')->whereNotNull('longitude');
                })->orWhereRaw('geometry IS NOT NULL');
            })
            ->with('entityType:id,code');

        // Subquery: last geo_match timestamp per entity
        $lastMatchSub = DB::table('sj_image_entity')
            ->select('entity_id', DB::raw('MAX(created_at) as last_matched_at'))
            ->where('source', 'geo_matched')
            ->groupBy('entity_id');

        // Priority 1: Entities never geo-matched
        $neverMatched = SjEntity::where('is_active', true)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('latitude')->whereNotNull('longitude');
                })->orWhereRaw('geometry IS NOT NULL');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('sj_image_entity')
                    ->whereColumn('sj_image_entity.entity_id', 'sj_entities.id')
                    ->where('source', 'geo_matched');
            })
            ->with('entityType:id,code')
            ->limit($limit)
            ->get();

        $remaining = $limit - $neverMatched->count();
        if ($remaining <= 0) {
            $this->line("Priority: {$neverMatched->count()} never-matched entities");
            return $neverMatched;
        }

        $excludeIds = $neverMatched->pluck('id');

        // Priority 2: Entities with new images since last match
        // (images.created_at > last geo_match created_at for that entity)
        $withNewImages = SjEntity::where('is_active', true)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('latitude')->whereNotNull('longitude');
                })->orWhereRaw('geometry IS NOT NULL');
            })
            ->whereNotIn('id', $excludeIds)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('sj_images')
                    ->whereColumn('sj_images.team_id', 'sj_entities.team_id')
                    ->whereNotNull('sj_images.latitude')
                    ->whereNotNull('sj_images.longitude')
                    ->where('sj_images.created_at', '>', function ($sub) {
                        $sub->select(DB::raw('COALESCE(MAX(pie.created_at), \'1970-01-01\')'))
                            ->from('sj_image_entity as pie')
                            ->whereColumn('pie.entity_id', 'sj_entities.id')
                            ->where('pie.source', 'geo_matched');
                    });
            })
            ->with('entityType:id,code')
            ->limit($remaining)
            ->get();

        $remaining -= $withNewImages->count();
        $excludeIds = $excludeIds->merge($withNewImages->pluck('id'));

        // Priority 3: Oldest last-matched first (rotating backfill)
        $rotating = collect();
        if ($remaining > 0) {
            $rotating = SjEntity::where('is_active', true)
                ->where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->whereNotNull('latitude')->whereNotNull('longitude');
                    })->orWhereRaw('geometry IS NOT NULL');
                })
                ->whereNotIn('id', $excludeIds)
                ->leftJoinSub($lastMatchSub, 'lm', 'lm.entity_id', '=', 'sj_entities.id')
                ->orderBy('lm.last_matched_at', 'asc')
                ->select('sj_entities.*')
                ->with('entityType:id,code')
                ->limit($remaining)
                ->get();
        }

        $this->line("Priority: {$neverMatched->count()} never-matched, {$withNewImages->count()} new-images, {$rotating->count()} rotating");

        return $neverMatched->concat($withNewImages)->concat($rotating);
    }

    protected function matchEntity(SjEntity $entity, bool $isDryRun): int
    {
        $typeCode = $entity->entityType?->code;
        $radius = $this->radiusMap[$typeCode] ?? $this->defaultRadius;

        $existingImageIds = DB::table('sj_image_entity')
            ->where('entity_id', $entity->id)
            ->pluck('sj_image_id');

        // Determine match strategy: geometry-based or haversine fallback
        $geoRow = DB::selectOne(
            'SELECT ST_AsGeoJSON(geometry) as geo, ST_GeometryType(geometry) as geo_type FROM sj_entities WHERE id = ?',
            [$entity->id]
        );

        $matchMethod = 'haversine';
        $nearbyImages = null;

        if ($geoRow?->geo && $geoRow->geo_type) {
            $geoJson = json_decode($geoRow->geo, true);
            $geoType = $geoRow->geo_type; // e.g. POLYGON, MULTIPOLYGON, LINESTRING

            if (in_array($geoType, ['POLYGON', 'MULTIPOLYGON'])) {
                $matchMethod = 'polygon';
                $nearbyImages = SjImage::withinGeoJson($geoJson)
                    ->where('team_id', $entity->team_id)
                    ->whereNotIn('id', $existingImageIds)
                    ->limit(50)
                    ->get();
            } elseif ($geoType === 'LINESTRING') {
                $matchMethod = 'linestring';
                $nearbyImages = SjImage::alongRoute($geoJson, 50)
                    ->where('team_id', $entity->team_id)
                    ->whereNotIn('id', $existingImageIds)
                    ->limit(50)
                    ->get();
            }
        }

        // Haversine fallback for Point entities or entities without geometry
        if ($nearbyImages === null) {
            if (!$entity->latitude || !$entity->longitude) {
                return 0;
            }
            $nearbyImages = SjImage::nearby($entity->latitude, $entity->longitude, $radius)
                ->where('team_id', $entity->team_id)
                ->whereNotIn('id', $existingImageIds)
                ->limit(50)
                ->get();
        }

        if ($nearbyImages->isEmpty()) {
            return 0;
        }

        if ($isDryRun) {
            $this->line("  [{$entity->name}] ({$typeCode}, {$matchMethod}): {$nearbyImages->count()} new matches");
            foreach ($nearbyImages->take(5) as $img) {
                if ($entity->latitude && $entity->longitude && $img->latitude && $img->longitude) {
                    $dist = $this->haversineMeters($entity->latitude, $entity->longitude, $img->latitude, $img->longitude);
                    $this->line("    - #{$img->id} \"{$img->title}\" — {$dist}m");
                } else {
                    $this->line("    - #{$img->id} \"{$img->title}\"");
                }
            }
            return $nearbyImages->count();
        }

        $rows = [];
        $now = now();
        $sortBase = 100;

        foreach ($nearbyImages as $i => $image) {
            $dist = ($entity->latitude && $entity->longitude && $image->latitude && $image->longitude)
                ? $this->haversineMeters($entity->latitude, $entity->longitude, $image->latitude, $image->longitude)
                : null;

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

        $this->line("  [{$entity->name}] ({$typeCode}, {$matchMethod}): {$nearbyImages->count()} matched");

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
