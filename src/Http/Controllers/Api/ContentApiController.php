<?php

namespace Platform\Syltjunkie\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Platform\Core\Http\Controllers\ApiController;
use Platform\Syltjunkie\Http\Controllers\Api\Concerns\ResolvesPublicTeam;
use Platform\Syltjunkie\Models\SjContentPiece;

class ContentApiController extends ApiController
{
    use ResolvesPublicTeam;

    public function index(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        $perPage = min((int) $request->query('per_page', 20), 100);

        $query = SjContentPiece::where('team_id', $teamId)
            ->where('status', 'published')
            ->with([
                'primaryKeyword',
                'entities:id,name,slug',
                'coverImage.contextFile',
            ]);

        // Filter: content_type
        if ($contentType = $request->query('content_type')) {
            $query->where('content_type', $contentType);
        }

        // Filter: entity_id
        if ($entityId = $request->query('entity_id')) {
            $query->whereHas('entities', fn ($q) => $q->where('sj_entities.id', $entityId));
        }

        // Filter: search
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        $query->orderByDesc('published_at');

        $paginator = $query->paginate($perPage);

        $paginator->getCollection()->transform(function (SjContentPiece $piece) {
            $primaryKw = $piece->primaryKeyword->first();

            return [
                'id' => $piece->id,
                'slug' => $piece->slug,
                'title' => $piece->title,
                'content_type' => $piece->content_type,
                'excerpt' => $piece->excerpt,
                'published_at' => $piece->published_at?->toIso8601String(),
                'primary_keyword' => $primaryKw ? [
                    'keyword' => $primaryKw->keyword,
                    'search_volume' => $primaryKw->search_volume,
                ] : null,
                'entities' => $piece->entities->map(fn ($e) => [
                    'name' => $e->name,
                    'slug' => $e->slug,
                ])->values(),
                'cover_image' => $piece->coverImage ? [
                    'url' => $piece->coverImage->url,
                    'thumbnail_url' => $piece->coverImage->thumbnail_url,
                ] : null,
            ];
        });

        return $this->paginated($paginator);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        $piece = SjContentPiece::where('team_id', $teamId)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with([
                'keywords',
                'entities:id,name,slug,entity_type_id',
                'entities.entityType:id,code,name',
                'entities.entityTypes:id,code,name',
                'entities.outgoingRelationships' => fn ($q) => $q->where('relation_type_id', 1)->where('is_active', true),
                'entities.outgoingRelationships.targetEntity:id,name,slug',
                'coverImage.contextFile',
                'images.contextFile',
                'contentBlocks',
            ])
            ->first();

        if (!$piece) {
            return $this->notFound('Content piece not found.');
        }

        $data = [
            'id' => $piece->id,
            'slug' => $piece->slug,
            'title' => $piece->title,
            'content_type' => $piece->content_type,
            'excerpt' => $piece->excerpt,
            'body_markdown' => $piece->body_markdown,
            'seo_title' => $piece->seo_title,
            'seo_description' => $piece->seo_description,
            'published_at' => $piece->published_at?->toIso8601String(),
            'keywords' => $piece->keywords->map(fn ($kw) => [
                'keyword' => $kw->keyword,
                'search_volume' => $kw->search_volume,
                'search_intent' => $kw->search_intent,
                'is_primary' => (bool) $kw->pivot->is_primary,
            ])->values(),
            'entities' => $piece->entities->map(fn ($e) => [
                'name' => $e->name,
                'slug' => $e->slug,
                'ort' => $e->outgoingRelationships->first()?->targetEntity?->name,
                'entity_type' => $e->entityType ? [
                    'code' => $e->entityType->code,
                    'name' => $e->entityType->name,
                ] : null,
                'entity_types' => $e->entityTypes->map(fn ($t) => [
                    'code' => $t->code,
                    'name' => $t->name,
                    'is_primary' => (bool) $t->pivot->is_primary,
                ])->values(),
            ])->values(),
            'cover_image' => $piece->coverImage ? [
                'url' => $piece->coverImage->url,
                'thumbnail_url' => $piece->coverImage->thumbnail_url,
            ] : null,
            'images' => $piece->images->map(fn ($img) => [
                'id' => $img->id,
                'title' => $img->title,
                'url' => $img->url,
                'thumbnail_url' => $img->thumbnail_url,
                'role' => $img->pivot->role,
            ])->values(),
            'blocks' => $piece->contentBlocks->map(fn ($b) => [
                'type' => $b->block_type,
                'content' => $b->content,
            ])->values(),
        ];

        return $this->success($data);
    }
}
