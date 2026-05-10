<?php

namespace Platform\Syltjunkie\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Platform\Core\Http\Controllers\ApiController;
use Platform\Syltjunkie\Http\Controllers\Api\Concerns\ResolvesPublicTeam;
use Platform\Syltjunkie\Models\SjImage;

class ImageApiController extends ApiController
{
    use ResolvesPublicTeam;

    public function index(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        $perPage = min((int) $request->query('per_page', 50), 200);

        $query = SjImage::where('team_id', $teamId)
            ->with(['contextFile', 'entities:id,name,slug']);

        // Filter: only geo-tagged images
        if ($request->boolean('geo_only')) {
            $query->whereNotNull('latitude')->whereNotNull('longitude');
        }

        // Filter: nearby a coordinate
        if ($request->filled('lat') && $request->filled('lng')) {
            $lat = (float) $request->query('lat');
            $lng = (float) $request->query('lng');
            $radius = (float) $request->query('radius', 2);
            $query->nearby($lat, $lng, $radius);
        }

        // Filter: by tag
        if ($tag = $request->query('tag')) {
            $query->withTag($tag);
        }

        // Filter: by entity slug
        if ($entity = $request->query('entity')) {
            $query->whereHas('entities', fn ($q) => $q->where('slug', $entity));
        }

        // Filter: search by title/description
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('photographer', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortField = $request->query('sort', 'created_at');
        $sortDir = $request->query('dir', 'desc');
        $allowedSorts = ['created_at', 'taken_at', 'title'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $paginator = $query->paginate($perPage);

        $paginator->getCollection()->transform(function (SjImage $image) {
            return [
                'id' => $image->id,
                'uuid' => $image->uuid,
                'title' => $image->title,
                'description' => $image->description,
                'photographer' => $image->photographer,
                'url' => $image->url,
                'thumbnail_url' => $image->thumbnail_url,
                'lat' => $image->latitude ? (float) $image->latitude : null,
                'lng' => $image->longitude ? (float) $image->longitude : null,
                'tags' => $image->tags ?? [],
                'taken_at' => $image->taken_at?->toDateString(),
                'entities' => $image->entities->map(fn ($e) => [
                    'slug' => $e->slug,
                    'name' => $e->name,
                ])->values(),
            ];
        });

        return $this->paginatedWithMeta($paginator);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        $image = SjImage::where('team_id', $teamId)
            ->where('uuid', $uuid)
            ->with([
                'contextFile',
                'entities:id,name,slug,latitude,longitude,entity_type_id',
                'entities.entityType:id,code,name,icon',
            ])
            ->first();

        if (!$image) {
            return $this->notFound('Image not found.');
        }

        return $this->success([
            'id' => $image->id,
            'uuid' => $image->uuid,
            'title' => $image->title,
            'description' => $image->description,
            'photographer' => $image->photographer,
            'url' => $image->url,
            'thumbnail_url' => $image->thumbnail_url,
            'lat' => $image->latitude ? (float) $image->latitude : null,
            'lng' => $image->longitude ? (float) $image->longitude : null,
            'tags' => $image->tags ?? [],
            'taken_at' => $image->taken_at?->toDateString(),
            'entities' => $image->entities->map(fn ($e) => [
                'slug' => $e->slug,
                'name' => $e->name,
                'lat' => $e->latitude,
                'lng' => $e->longitude,
                'type' => $e->entityType ? [
                    'code' => $e->entityType->code,
                    'name' => $e->entityType->name,
                    'icon' => $e->entityType->icon,
                ] : null,
            ])->values(),
        ]);
    }

    protected function paginatedWithMeta($paginator): JsonResponse
    {
        $response = parent::paginated($paginator);
        $data = $response->getData(true);
        $data['data']['pagination']['has_more'] = $paginator->hasMorePages();
        $data['data']['pagination']['next_page_url'] = $paginator->nextPageUrl();
        return response()->json($data);
    }
}
