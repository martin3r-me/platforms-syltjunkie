<?php

namespace Platform\Syltjunkie\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Platform\Core\Http\Controllers\ApiController;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityOwner;

class OwnerApiController extends ApiController
{
    protected function ownerEmail(Request $request): string
    {
        return $request->attributes->get('sj_owner_email');
    }

    protected function ownerTeamId(Request $request): int
    {
        return $request->attributes->get('sj_owner_team_id');
    }

    public function me(Request $request): JsonResponse
    {
        $email = $this->ownerEmail($request);
        $teamId = $this->ownerTeamId($request);

        $owner = SjEntityOwner::where('team_id', $teamId)
            ->where('email', $email)
            ->approved()
            ->first();

        $entities = SjEntityOwner::entitiesForOwner($teamId, $email);

        return $this->success([
            'email' => $email,
            'name' => $owner?->name,
            'last_login_at' => $owner?->last_login_at?->toIso8601String(),
            'entities' => $entities->map(fn (SjEntity $entity) => [
                'slug' => $entity->slug,
                'name' => $entity->name,
            ])->values(),
        ]);
    }

    public function entity(Request $request, string $slug): JsonResponse
    {
        $email = $this->ownerEmail($request);
        $teamId = $this->ownerTeamId($request);

        $entity = $this->resolveOwnedEntity($teamId, $email, $slug);

        if (!$entity) {
            return $this->notFound('Entity nicht gefunden oder kein Zugriff.');
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

    public function updateEntity(Request $request, string $slug): JsonResponse
    {
        $email = $this->ownerEmail($request);
        $teamId = $this->ownerTeamId($request);

        $entity = $this->resolveOwnedEntity($teamId, $email, $slug);

        if (!$entity) {
            return $this->notFound('Entity nicht gefunden oder kein Zugriff.');
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

    protected function resolveOwnedEntity(int $teamId, string $email, string $slug): ?SjEntity
    {
        $entityIds = SjEntityOwner::where('team_id', $teamId)
            ->where('email', $email)
            ->approved()
            ->pluck('entity_id');

        return SjEntity::where('team_id', $teamId)
            ->where('slug', $slug)
            ->whereIn('id', $entityIds)
            ->first();
    }
}
