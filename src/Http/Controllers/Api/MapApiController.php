<?php

namespace Platform\Syltjunkie\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Platform\Core\Http\Controllers\ApiController;
use Platform\Syltjunkie\Http\Controllers\Api\Concerns\ResolvesPublicTeam;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityType;

class MapApiController extends ApiController
{
    use ResolvesPublicTeam;

    public function index(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        $query = SjEntity::where('team_id', $teamId)
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with([
                'entityType:id,code,name,color,icon,group_id',
                'entityType.group:id,code,prefix',
                'images' => fn ($q) => $q->wherePivot('is_primary', true)->limit(1),
                'images.contextFile',
            ]);

        // Filter: type
        if ($type = $request->query('type')) {
            $typeId = SjEntityType::where('team_id', $teamId)
                ->where('code', $type)
                ->value('id');
            $query->where('entity_type_id', $typeId);
        }

        // Filter: group
        if ($group = $request->query('group')) {
            $query->whereHas('entityType.group', fn ($q) => $q->where('code', $group));
        }

        // Filter: ort
        if ($ort = $request->query('ort')) {
            $query->where('ort', $ort);
        }

        $entities = $query->get();

        $data = $entities->map(function (SjEntity $entity) {
            $primaryImage = $entity->images->first();

            return [
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
                'primary_image' => $primaryImage ? [
                    'url' => $primaryImage->url,
                    'thumbnail_url' => $primaryImage->thumbnail_url,
                ] : null,
            ];
        })->values();

        return $this->success($data);
    }
}
