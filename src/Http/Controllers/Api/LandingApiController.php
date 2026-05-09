<?php

namespace Platform\Syltjunkie\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Platform\Core\Http\Controllers\ApiController;
use Platform\Syltjunkie\Http\Controllers\Api\Concerns\ResolvesPublicTeam;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityType;
use Platform\Syltjunkie\Models\SjEntityTypeGroup;

class LandingApiController extends ApiController
{
    use ResolvesPublicTeam;

    public function index(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        $groups = SjEntityTypeGroup::where('team_id', $teamId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['entityTypes' => function ($q) use ($teamId) {
                $q->where('team_id', $teamId)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->withCount('entities');
            }])
            ->get();

        // Build groups data (only groups that have entities)
        $groupsData = [];
        $entitiesByGroup = [];
        $mapGroups = [];
        $typeLabels = [];

        foreach ($groups as $group) {
            $entityCount = $group->entityTypes->sum('entities_count');

            if ($entityCount === 0) {
                continue;
            }

            $types = $group->entityTypes->map(fn ($type) => [
                'code' => $type->code,
                'name' => $type->name,
                'icon' => $type->icon,
                'color' => $type->color,
                'entity_count' => $type->entities_count,
            ])->values();

            $groupData = [
                'code' => $group->code,
                'label' => $group->name,
                'nav_label' => $group->nav_label ?? $group->name,
                'singular' => $group->singular ?? $group->name,
                'prefix' => $group->prefix ?? $group->code,
                'icon' => $group->icon,
                'color' => $group->color,
                'template' => $group->template ?? 'default',
                'sort_order' => $group->sort_order,
                'show_on_map' => $group->show_on_map,
                'entity_count' => $entityCount,
                'types' => $types,
            ];

            $groupsData[] = $groupData;

            if ($group->show_on_map) {
                $mapGroups[] = $groupData;
            }

            // Collect type labels
            foreach ($group->entityTypes as $type) {
                $typeLabels[$type->code] = $type->name;
            }

            // Load max 8 entities per group for carousels
            $typeIds = $group->entityTypes->pluck('id');
            $entities = SjEntity::where('team_id', $teamId)
                ->where('is_active', true)
                ->whereIn('entity_type_id', $typeIds)
                ->with([
                    'entityType:id,code,name,color,icon,group_id',
                    'entityType.group:id,code,prefix',
                    'images' => fn ($q) => $q->wherePivot('is_primary', true)->limit(1),
                    'images.contextFile',
                ])
                ->orderByDesc('created_at')
                ->limit(8)
                ->get();

            $entitiesByGroup[$group->code] = $entities->map(
                fn (SjEntity $entity) => $this->formatEntityCompact($entity)
            )->values();
        }

        // Map entities (entities with lat/lng from map groups)
        $mapGroupIds = $groups->where('show_on_map', true)->pluck('id');
        $mapTypeIds = SjEntityType::where('team_id', $teamId)
            ->whereIn('group_id', $mapGroupIds)
            ->where('is_active', true)
            ->pluck('id');

        $mapEntities = SjEntity::where('team_id', $teamId)
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereIn('entity_type_id', $mapTypeIds)
            ->with([
                'entityType:id,code,name,color,icon,group_id',
                'entityType.group:id,code,prefix',
            ])
            ->get()
            ->map(fn (SjEntity $entity) => [
                'id' => $entity->id,
                'name' => $entity->name,
                'slug' => $entity->slug,
                'lat' => $entity->latitude,
                'lng' => $entity->longitude,
                'ort' => $entity->ort,
                'group' => $entity->entityType?->group?->code,
                'group_prefix' => $entity->entityType?->group?->prefix ?? $entity->entityType?->group?->code,
                'type' => $entity->entityType ? [
                    'code' => $entity->entityType->code,
                    'name' => $entity->entityType->name,
                    'color' => $entity->entityType->color,
                    'icon' => $entity->entityType->icon,
                ] : null,
            ])->values();

        $totalEntities = SjEntity::where('team_id', $teamId)
            ->where('is_active', true)
            ->count();

        return $this->success([
            'groups' => $groupsData,
            'entities_by_group' => $entitiesByGroup,
            'map_groups' => $mapGroups,
            'map_entities' => $mapEntities,
            'total_entities' => $totalEntities,
            'type_labels' => $typeLabels,
        ]);
    }

    protected function formatEntityCompact(SjEntity $entity): array
    {
        $primaryImage = $entity->images->first();
        $tags = $entity->extra_fields['tags'] ?? [];

        return [
            'id' => $entity->id,
            'slug' => $entity->slug,
            'name' => $entity->name,
            'description' => $entity->description,
            'ort' => $entity->ort,
            'latitude' => $entity->latitude,
            'longitude' => $entity->longitude,
            'entity_type_code' => $entity->entityType?->code,
            'group' => $entity->entityType?->group?->code,
            'group_prefix' => $entity->entityType?->group?->prefix ?? $entity->entityType?->group?->code,
            'type_label' => $entity->entityType?->name,
            'tags' => $tags,
            'entity_type' => $entity->entityType ? [
                'code' => $entity->entityType->code,
                'name' => $entity->entityType->name,
                'color' => $entity->entityType->color,
                'icon' => $entity->entityType->icon,
            ] : null,
            'primary_image' => $primaryImage ? [
                'url' => $primaryImage->url,
                'thumbnail_url' => $primaryImage->thumbnail_url,
            ] : null,
        ];
    }
}
