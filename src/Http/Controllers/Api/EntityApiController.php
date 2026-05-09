<?php

namespace Platform\Syltjunkie\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Platform\Core\Http\Controllers\ApiController;
use Platform\Syltjunkie\Http\Controllers\Api\Concerns\ResolvesPublicTeam;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityType;

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
                'entityType:id,code,name,color,icon',
                'images' => fn ($q) => $q->wherePivot('is_primary', true)->limit(1),
                'images.contextFile',
            ]);

        // Filter: type (entity_type code)
        if ($type = $request->query('type')) {
            $typeId = SjEntityType::where('team_id', $teamId)
                ->where('code', $type)
                ->value('id');
            $query->where('entity_type_id', $typeId);
        }

        // Filter: group (entity_type_group code)
        if ($group = $request->query('group')) {
            $query->whereHas('entityType.group', fn ($q) => $q->where('code', $group));
        }

        // Filter: ort
        if ($ort = $request->query('ort')) {
            $query->where('ort', $ort);
        }

        // Filter: search
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('ort', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortField = $request->query('sort', 'name');
        $sortDir = $request->query('dir', 'asc');
        $allowedSorts = ['name', 'created_at', 'ort'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDir === 'desc' ? 'desc' : 'asc');
        }

        $paginator = $query->paginate($perPage);

        // Transform results
        $paginator->getCollection()->transform(function (SjEntity $entity) {
            $primaryImage = $entity->images->first();

            return [
                'id' => $entity->id,
                'slug' => $entity->slug,
                'name' => $entity->name,
                'description' => $entity->description,
                'ort' => $entity->ort,
                'latitude' => $entity->latitude,
                'longitude' => $entity->longitude,
                'status' => $entity->status,
                'season' => $entity->season,
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
        });

        return $this->paginated($paginator);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        $entity = SjEntity::where('team_id', $teamId)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with([
                'entityType.group',
                'images.contextFile',
                'outgoingRelationships.relationType',
                'outgoingRelationships.targetEntity:id,name,slug,entity_type_id',
                'outgoingRelationships.targetEntity.entityType:id,code,name',
                'incomingRelationships.relationType',
                'incomingRelationships.sourceEntity:id,name,slug,entity_type_id',
                'incomingRelationships.sourceEntity.entityType:id,code,name',
                'keywords',
                'contentPieces' => fn ($q) => $q->where('status', 'published'),
                'contentPieces.coverImage.contextFile',
                'latestScore',
                'ctaConfigs' => fn ($q) => $q->where('is_active', true),
                'entityUrls' => fn ($q) => $q->where('is_active', true),
            ])
            ->first();

        if (!$entity) {
            return $this->notFound('Entity not found.');
        }

        $data = [
            'id' => $entity->id,
            'slug' => $entity->slug,
            'name' => $entity->name,
            'description' => $entity->description,
            'ort' => $entity->ort,
            'latitude' => $entity->latitude,
            'longitude' => $entity->longitude,
            'status' => $entity->status,
            'season' => $entity->season,
            'entity_type' => $entity->entityType ? [
                'code' => $entity->entityType->code,
                'name' => $entity->entityType->name,
                'icon' => $entity->entityType->icon,
                'color' => $entity->entityType->color,
                'group' => $entity->entityType->group ? [
                    'code' => $entity->entityType->group->code,
                    'name' => $entity->entityType->group->name,
                ] : null,
            ] : null,
            'extra_fields' => $entity->extra_fields,
            'images' => $entity->images->map(fn ($img) => [
                'id' => $img->id,
                'title' => $img->title,
                'url' => $img->url,
                'thumbnail_url' => $img->thumbnail_url,
                'is_primary' => (bool) $img->pivot->is_primary,
            ])->values(),
            'relationships' => $this->formatRelationships($entity),
            'keywords' => $entity->keywords->map(fn ($kw) => [
                'keyword' => $kw->keyword,
                'search_volume' => $kw->search_volume,
                'search_intent' => $kw->search_intent,
                'trends_sparkline' => $kw->trends_sparkline,
            ])->values(),
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
        ];

        return $this->success($data);
    }

    protected function formatRelationships(SjEntity $entity): array
    {
        $relationships = [];

        foreach ($entity->outgoingRelationships as $rel) {
            if (!$rel->targetEntity || !$rel->is_active) {
                continue;
            }
            $relationships[] = [
                'direction' => 'outgoing',
                'type' => $rel->relationType?->code,
                'label' => $rel->relationType?->name,
                'entity' => [
                    'slug' => $rel->targetEntity->slug,
                    'name' => $rel->targetEntity->name,
                    'type' => $rel->targetEntity->entityType?->code,
                ],
            ];
        }

        foreach ($entity->incomingRelationships as $rel) {
            if (!$rel->sourceEntity || !$rel->is_active) {
                continue;
            }
            $relationships[] = [
                'direction' => 'incoming',
                'type' => $rel->relationType?->code,
                'label' => $rel->relationType?->inverse_name ?? $rel->relationType?->name,
                'entity' => [
                    'slug' => $rel->sourceEntity->slug,
                    'name' => $rel->sourceEntity->name,
                    'type' => $rel->sourceEntity->entityType?->code,
                ],
            ];
        }

        return $relationships;
    }
}
