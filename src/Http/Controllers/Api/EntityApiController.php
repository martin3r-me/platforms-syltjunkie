<?php

namespace Platform\Syltjunkie\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Platform\Core\Http\Controllers\ApiController;
use Platform\Syltjunkie\Http\Controllers\Api\Concerns\ResolvesPublicTeam;
use Platform\Syltjunkie\Models\SjEntity;

class EntityApiController extends ApiController
{
    use ResolvesPublicTeam;

    public function index(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        $perPage = min((int) $request->query('per_page', 50), 200);

        $query = SjEntity::where('team_id', $teamId)
            ->where('is_active', true)
            ->with([
                'entityType:id,code,name,color,icon,group_id',
                'entityType.group:id,code,prefix',
                'entityTypes:id,code,name,color,icon',
                'images' => fn ($q) => $q->wherePivot('is_primary', true)->limit(1),
                'images.contextFile',
                'outgoingRelationships' => fn ($q) => $q->where('relation_type_id', 1)->where('is_active', true),
                'outgoingRelationships.targetEntity:id,name,slug',
            ]);

        // Filter: type (entity_type code) — searches across all assigned types via pivot
        if ($type = $request->query('type')) {
            $query->whereHas('entityTypes', fn ($q) => $q->where('code', $type)->where('team_id', $teamId));
        }

        // Filter: group (entity_type_group code)
        if ($group = $request->query('group')) {
            $query->whereHas('entityType.group', fn ($q) => $q->where('code', $group));
        }

        // Filter: ort (by slug of the related Ort entity)
        if ($ort = $request->query('ort')) {
            $query->whereHas('outgoingRelationships', fn ($q) => $q
                ->where('relation_type_id', 1)
                ->where('is_active', true)
                ->whereHas('targetEntity', fn ($q2) => $q2->where('slug', $ort))
            );
        }

        // Filter: search
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('outgoingRelationships', fn ($rq) => $rq
                        ->where('relation_type_id', 1)
                        ->where('is_active', true)
                        ->whereHas('targetEntity', fn ($tq) => $tq->where('name', 'like', "%{$search}%"))
                    );
            });
        }

        // Sorting
        $sortField = $request->query('sort', 'name');
        $sortDir = $request->query('dir', 'asc');
        $allowedSorts = ['name', 'created_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDir === 'desc' ? 'desc' : 'asc');
        }

        $paginator = $query->paginate($perPage);

        // Transform results
        $paginator->getCollection()->transform(function (SjEntity $entity) {
            $primaryImage = $entity->images->first();
            $tags = $entity->extra_fields['tags'] ?? [];
            $ortRelationship = $entity->outgoingRelationships->first();

            return [
                'id' => $entity->id,
                'slug' => $entity->slug,
                'name' => $entity->name,
                'description' => $entity->description,
                'ort' => $ortRelationship?->targetEntity?->name,
                'lat' => $entity->latitude,
                'lng' => $entity->longitude,
                'status' => $entity->status,
                'season' => $entity->season,
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
                'entity_types' => $entity->entityTypes->map(fn ($t) => [
                    'code' => $t->code,
                    'name' => $t->name,
                    'color' => $t->color,
                    'icon' => $t->icon,
                    'is_primary' => (bool) $t->pivot->is_primary,
                ])->values(),
                'primary_image' => $primaryImage ? [
                    'url' => $primaryImage->url,
                    'thumbnail_url' => $primaryImage->thumbnail_url,
                ] : null,
            ];
        });

        return $this->paginatedWithMeta($paginator);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        $entity = SjEntity::where('team_id', $teamId)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with([
                'entityType.group',
                'entityTypes',
                'images.contextFile',
                'outgoingRelationships.relationType',
                'outgoingRelationships.targetEntity:id,name,slug,description,latitude,longitude,entity_type_id',
                'outgoingRelationships.targetEntity.entityType:id,code,name,group_id',
                'outgoingRelationships.targetEntity.entityType.group:id,code,prefix',
                'outgoingRelationships.targetEntity.images' => fn ($q) => $q->wherePivot('is_primary', true)->limit(1),
                'outgoingRelationships.targetEntity.images.contextFile',
                'incomingRelationships.relationType',
                'incomingRelationships.sourceEntity:id,name,slug,description,latitude,longitude,entity_type_id',
                'incomingRelationships.sourceEntity.entityType:id,code,name,group_id',
                'incomingRelationships.sourceEntity.entityType.group:id,code,prefix',
                'incomingRelationships.sourceEntity.images' => fn ($q) => $q->wherePivot('is_primary', true)->limit(1),
                'incomingRelationships.sourceEntity.images.contextFile',
                'keywords',
                'contentPieces' => fn ($q) => $q->where('status', 'published'),
                'contentPieces.coverImage.contextFile',
                'latestScore',
                'ctaConfigs' => fn ($q) => $q->where('is_active', true),
                'entityUrls' => fn ($q) => $q->where('is_active', true),
                'owner',
                'events' => fn ($q) => $q->where('starts_at', '>=', now())
                    ->where('status', '!=', 'cancelled')
                    ->orderBy('starts_at')
                    ->limit(10),
            ])
            ->first();

        if (!$entity) {
            return $this->notFound('Entity not found.');
        }

        $tags = $entity->extra_fields['tags'] ?? [];

        $ortRelationship = $entity->outgoingRelationships
            ->where('relation_type_id', 1)
            ->where('is_active', true)
            ->first();

        $data = [
            'id' => $entity->id,
            'slug' => $entity->slug,
            'name' => $entity->name,
            'description' => $entity->description,
            'ort' => $ortRelationship?->targetEntity?->name,
            'lat' => $entity->latitude,
            'lng' => $entity->longitude,
            'status' => $entity->status,
            'season' => $entity->season,
            'entity_type_code' => $entity->entityType?->code,
            'group' => $entity->entityType?->group?->code,
            'group_prefix' => $entity->entityType?->group?->prefix ?? $entity->entityType?->group?->code,
            'type_label' => $entity->entityType?->name,
            'tags' => $tags,
            'entity_type' => $entity->entityType ? [
                'code' => $entity->entityType->code,
                'name' => $entity->entityType->name,
                'icon' => $entity->entityType->icon,
                'color' => $entity->entityType->color,
                'group' => $entity->entityType->group ? [
                    'code' => $entity->entityType->group->code,
                    'name' => $entity->entityType->group->name,
                    'prefix' => $entity->entityType->group->prefix ?? $entity->entityType->group->code,
                ] : null,
            ] : null,
            'entity_types' => $entity->entityTypes->map(fn ($t) => [
                'code' => $t->code,
                'name' => $t->name,
                'icon' => $t->icon,
                'color' => $t->color,
                'is_primary' => (bool) $t->pivot->is_primary,
            ])->values(),
            'extra_fields' => [
                'tags' => $entity->extra_fields['tags'] ?? [],
                'google_is_claimed' => $entity->extra_fields['google_is_claimed'] ?? null,
                'google_category' => $entity->extra_fields['google_category'] ?? null,
                'google_current_status' => $entity->extra_fields['google_current_status'] ?? null,
            ],
            'completeness' => [
                'score' => $this->calculateCompleteness($entity),
                'missing' => $this->getMissingFields($entity),
            ],
            'images' => $entity->images->where('pivot.source', '!=', 'geo_matched')->map(fn ($img) => [
                'id' => $img->id,
                'title' => $img->title,
                'url' => $img->url,
                'thumbnail_url' => $img->thumbnail_url,
                'is_primary' => (bool) $img->pivot->is_primary,
            ])->values(),
            'nearby_images' => $entity->images->where('pivot.source', 'geo_matched')->map(fn ($img) => [
                'id' => $img->id,
                'title' => $img->title,
                'url' => $img->url,
                'thumbnail_url' => $img->thumbnail_url,
                'distance_m' => $img->pivot->distance_m,
            ])->sortBy('pivot.distance_m')->values(),
            'relationships' => $this->formatRelationships($entity),
            'keywords' => $entity->keywords->sortByDesc('search_volume')->take(5)->map(fn ($kw) => [
                'keyword' => $kw->keyword,
                'search_volume' => $kw->search_volume,
                'search_intent' => $kw->search_intent,
                'trends_sparkline' => $kw->trends_sparkline,
            ])->values(),
            'keywords_total' => $entity->keywords->count(),
            'content_pieces' => $entity->contentPieces->map(fn ($cp) => [
                'slug' => $cp->slug,
                'title' => $cp->title,
                'content_type' => $cp->content_type,
                'excerpt' => $cp->excerpt,
                'cover_image' => $cp->coverImage ? [
                    'url' => $cp->coverImage->url,
                    'thumbnail_url' => $cp->coverImage->thumbnail_url,
                ] : null,
            ])->values(),
            'latest_score' => $entity->latestScore ? [
                'visibility_score' => $entity->latestScore->visibility_score,
                'avg_review_rating' => $entity->latestScore->avg_review_rating,
                'total_review_count' => $entity->latestScore->total_review_count,
                'estimated_monthly_traffic' => $entity->latestScore->estimated_monthly_traffic,
            ] : null,
            'cta_configs' => $entity->ctaConfigs->map(fn ($cta) => [
                'cta_type' => $cta->cta_type,
                'target_url' => $cta->target_url,
                'phone' => $cta->phone,
                'is_active' => $cta->is_active,
            ])->values(),
            'entity_urls' => $entity->entityUrls->map(fn ($url) => [
                'url' => $url->url,
                'platform' => $url->platform,
                'is_primary' => $url->is_primary,
            ])->values(),
            'links' => $this->buildLinksObject($entity->entityUrls),
            'upcoming_events' => $entity->events->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'starts_at' => $e->starts_at->toIso8601String(),
                'ends_at' => $e->ends_at?->toIso8601String(),
                'is_all_day' => $e->is_all_day,
                'location_detail' => $e->location_detail,
                'status' => $e->status,
            ])->values(),
            'upcoming_events_total' => $entity->upcomingEvents()->count(),
            'has_owner' => $entity->owner !== null,
        ];

        return $this->success($data);
    }

    public function events(Request $request, string $slug): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        $entity = SjEntity::where('team_id', $teamId)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$entity) {
            return $this->notFound('Entity not found.');
        }

        $perPage = min((int) $request->query('per_page', 50), 200);

        $query = $entity->events()->orderBy('starts_at');

        if (!$request->boolean('past')) {
            $query->where('starts_at', '>=', now());
        }

        $paginator = $query->paginate($perPage);

        $paginator->getCollection()->transform(fn ($e) => [
            'id' => $e->id,
            'uuid' => $e->uuid,
            'title' => $e->title,
            'description' => $e->description,
            'starts_at' => $e->starts_at->toIso8601String(),
            'ends_at' => $e->ends_at?->toIso8601String(),
            'is_all_day' => $e->is_all_day,
            'location_detail' => $e->location_detail,
            'status' => $e->status,
            'metadata' => $e->metadata,
        ]);

        return $this->paginatedWithMeta($paginator);
    }

    public function keywords(Request $request, string $slug): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        $entity = SjEntity::where('team_id', $teamId)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$entity) {
            return $this->notFound('Entity not found.');
        }

        $perPage = min((int) $request->query('per_page', 50), 200);

        $paginator = $entity->keywords()
            ->orderByDesc('search_volume')
            ->paginate($perPage);

        $paginator->getCollection()->transform(fn ($kw) => [
            'keyword' => $kw->keyword,
            'search_volume' => $kw->search_volume,
            'search_intent' => $kw->search_intent,
            'trends_sparkline' => $kw->trends_sparkline,
            'attribution_type' => $kw->pivot->attribution_type,
            'confidence' => $kw->pivot->confidence,
        ]);

        return $this->paginatedWithMeta($paginator);
    }

    protected function formatRelationships(SjEntity $entity): array
    {
        $relationships = [];

        foreach ($entity->outgoingRelationships as $rel) {
            if (!$rel->targetEntity || !$rel->is_active) {
                continue;
            }
            $primaryImage = $rel->targetEntity->images->first();
            $relationships[] = [
                'direction' => 'outgoing',
                'type' => $rel->relationType?->code,
                'label' => $rel->relationType?->name,
                'entity' => [
                    'slug' => $rel->targetEntity->slug,
                    'name' => $rel->targetEntity->name,
                    'description' => $rel->targetEntity->description,
                    'lat' => $rel->targetEntity->latitude,
                    'lng' => $rel->targetEntity->longitude,
                    'type' => $rel->targetEntity->entityType?->code,
                    'type_label' => $rel->targetEntity->entityType?->name,
                    'group' => $rel->targetEntity->entityType?->group?->code,
                    'group_prefix' => $rel->targetEntity->entityType?->group?->prefix ?? $rel->targetEntity->entityType?->group?->code,
                    'primary_image' => $primaryImage ? [
                        'url' => $primaryImage->url,
                        'thumbnail_url' => $primaryImage->thumbnail_url,
                    ] : null,
                ],
            ];
        }

        foreach ($entity->incomingRelationships as $rel) {
            if (!$rel->sourceEntity || !$rel->is_active) {
                continue;
            }
            $primaryImage = $rel->sourceEntity->images->first();
            $relationships[] = [
                'direction' => 'incoming',
                'type' => $rel->relationType?->code,
                'label' => $rel->relationType?->inverse_name ?? $rel->relationType?->name,
                'entity' => [
                    'slug' => $rel->sourceEntity->slug,
                    'name' => $rel->sourceEntity->name,
                    'description' => $rel->sourceEntity->description,
                    'lat' => $rel->sourceEntity->latitude,
                    'lng' => $rel->sourceEntity->longitude,
                    'type' => $rel->sourceEntity->entityType?->code,
                    'type_label' => $rel->sourceEntity->entityType?->name,
                    'group' => $rel->sourceEntity->entityType?->group?->code,
                    'group_prefix' => $rel->sourceEntity->entityType?->group?->prefix ?? $rel->sourceEntity->entityType?->group?->code,
                    'primary_image' => $primaryImage ? [
                        'url' => $primaryImage->url,
                        'thumbnail_url' => $primaryImage->thumbnail_url,
                    ] : null,
                ],
            ];
        }

        return $relationships;
    }

    protected function buildLinksObject($entityUrls): array
    {
        $links = [];
        foreach ($entityUrls as $url) {
            $links[$url->platform] = [
                'url' => $url->url,
                'is_primary' => $url->is_primary,
            ];
        }
        return $links;
    }

    protected function calculateCompleteness(SjEntity $entity): int
    {
        $fields = [
            'description' => !empty($entity->description),
            'coordinates' => $entity->latitude !== null && $entity->longitude !== null,
            'images' => $entity->images->isNotEmpty(),
            'entity_urls' => $entity->entityUrls->isNotEmpty(),
            'google_business' => !empty($entity->extra_fields['google_is_claimed']),
            'tags' => !empty($entity->extra_fields['tags']),
        ];

        $filled = count(array_filter($fields));
        $total = count($fields);

        return (int) round(($filled / $total) * 100);
    }

    protected function getMissingFields(SjEntity $entity): array
    {
        $missing = [];

        if (empty($entity->description)) {
            $missing[] = 'description';
        }
        if ($entity->latitude === null || $entity->longitude === null) {
            $missing[] = 'coordinates';
        }
        if ($entity->images->isEmpty()) {
            $missing[] = 'images';
        }
        if ($entity->entityUrls->isEmpty()) {
            $missing[] = 'entity_urls';
        }
        if (empty($entity->extra_fields['google_is_claimed'])) {
            $missing[] = 'google_business';
        }
        if (empty($entity->extra_fields['tags'])) {
            $missing[] = 'tags';
        }

        return $missing;
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
