<?php

namespace Platform\Syltjunkie\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Platform\Core\Http\Controllers\ApiController;
use Platform\Syltjunkie\Models\SjEntityOwner;

class OwnerApiController extends ApiController
{
    protected function owner(Request $request): SjEntityOwner
    {
        return $request->attributes->get('sj_owner');
    }

    public function me(Request $request): JsonResponse
    {
        $owner = $this->owner($request);
        $owner->load('entity:id,name,slug');

        return $this->success([
            'email' => $owner->email,
            'name' => $owner->name,
            'entity_name' => $owner->entity?->name,
            'last_login_at' => $owner->last_login_at?->toIso8601String(),
        ]);
    }

    public function entity(Request $request): JsonResponse
    {
        $owner = $this->owner($request);
        $entity = $owner->entity;

        if (!$entity) {
            return $this->notFound('Keine Entity zugeordnet.');
        }

        $entity->load([
            'entityType:id,code,name,color,icon',
            'images.contextFile',
            'entityUrls' => fn ($q) => $q->where('is_active', true),
            'outgoingRelationships' => fn ($q) => $q->where('relation_type_id', 1)->where('is_active', true),
            'outgoingRelationships.targetEntity:id,name,slug',
        ]);

        return $this->success([
            'id' => $entity->id,
            'slug' => $entity->slug,
            'name' => $entity->name,
            'description' => $entity->description,
            'lat' => $entity->latitude,
            'lng' => $entity->longitude,
            'status' => $entity->status,
            'season' => $entity->season,
            'extra_fields' => $entity->extra_fields,
            'entity_type' => $entity->entityType ? [
                'code' => $entity->entityType->code,
                'name' => $entity->entityType->name,
            ] : null,
            'ort' => $entity->outgoingRelationships->first()?->targetEntity?->name,
            'images' => $entity->images->map(fn ($img) => [
                'id' => $img->id,
                'title' => $img->title,
                'url' => $img->url,
                'thumbnail_url' => $img->thumbnail_url,
                'is_primary' => (bool) $img->pivot->is_primary,
            ])->values(),
            'entity_urls' => $entity->entityUrls->map(fn ($url) => [
                'url' => $url->url,
                'platform' => $url->platform,
            ])->values(),
        ]);
    }

    public function updateEntity(Request $request): JsonResponse
    {
        $owner = $this->owner($request);
        $entity = $owner->entity;

        if (!$entity) {
            return $this->notFound('Keine Entity zugeordnet.');
        }

        $validated = $request->validate([
            'description' => 'nullable|string|max:5000',
            'extra_fields' => 'nullable|array',
            'season' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:aktiv,saisonal_geschlossen,dauerhaft_geschlossen',
        ]);

        $entity->update($validated);

        return $this->success(null, 'Entity aktualisiert.');
    }
}
