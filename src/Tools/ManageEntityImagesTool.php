<?php

namespace Platform\Syltjunkie\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjImage;
use Platform\Syltjunkie\Tools\Concerns\ResolvesSyltjunkieTeam;

class ManageEntityImagesTool implements ToolContract, ToolMetadataContract
{
    use ResolvesSyltjunkieTeam;

    public function getName(): string
    {
        return 'syltjunkie.entity_images.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /syltjunkie/entity-images - Bilder an Entity anhängen/entfernen, Primary setzen, Reihenfolge ändern. ERFORDERLICH: entity_id, action.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Entity.',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID.',
                ],
                'action' => [
                    'type' => 'string',
                    'enum' => ['attach', 'detach', 'set_primary', 'reorder'],
                    'description' => 'ERFORDERLICH: Aktion. attach=Bilder anhängen, detach=entfernen, set_primary=Hauptbild setzen, reorder=Reihenfolge ändern.',
                ],
                'image_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'ERFORDERLICH für attach/detach/reorder: Array von Bild-IDs.',
                ],
                'primary_image_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH für set_primary: ID des Hauptbildes.',
                ],
            ],
            'required' => ['entity_id', 'action'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $entity = SjEntity::where('team_id', $rootTeamId)->find($arguments['entity_id'] ?? 0);
            if (!$entity) {
                return ToolResult::error('NOT_FOUND', 'Entity nicht gefunden.');
            }

            $action = $arguments['action'] ?? '';

            switch ($action) {
                case 'attach':
                    return $this->handleAttach($entity, $arguments, $rootTeamId);

                case 'detach':
                    return $this->handleDetach($entity, $arguments);

                case 'set_primary':
                    return $this->handleSetPrimary($entity, $arguments);

                case 'reorder':
                    return $this->handleReorder($entity, $arguments);

                default:
                    return ToolResult::error('INVALID_ACTION', 'Ungültige Aktion: ' . $action);
            }
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    private function handleAttach(SjEntity $entity, array $arguments, int $rootTeamId): ToolResult
    {
        $imageIds = $arguments['image_ids'] ?? [];
        if (empty($imageIds)) {
            return ToolResult::error('VALIDATION_ERROR', 'image_ids ist erforderlich für attach.');
        }

        // Validate images exist and belong to same team
        $images = SjImage::where('team_id', $rootTeamId)
            ->whereIn('id', $imageIds)
            ->pluck('id')
            ->toArray();

        if (count($images) !== count($imageIds)) {
            return ToolResult::error('VALIDATION_ERROR', 'Nicht alle Bilder gefunden oder gehören nicht zum selben Team.');
        }

        // Get current max sort_order
        $maxSort = $entity->images()->max('sj_image_entity.sort_order') ?? -1;

        // Check if entity has any primary image
        $hasPrimary = $entity->images()->wherePivot('is_primary', true)->exists();

        $syncData = [];
        foreach ($imageIds as $index => $imageId) {
            $syncData[$imageId] = [
                'sort_order' => $maxSort + 1 + $index,
                'source' => 'manual',
                'is_primary' => !$hasPrimary && $index === 0,
            ];
        }

        $entity->images()->syncWithoutDetaching($syncData);

        return $this->respondWithImages($entity, 'Bilder angehängt.');
    }

    private function handleDetach(SjEntity $entity, array $arguments): ToolResult
    {
        $imageIds = $arguments['image_ids'] ?? [];
        if (empty($imageIds)) {
            return ToolResult::error('VALIDATION_ERROR', 'image_ids ist erforderlich für detach.');
        }

        // Check if primary is being removed
        $primaryRemoved = $entity->images()
            ->wherePivot('is_primary', true)
            ->whereIn('sj_images.id', $imageIds)
            ->exists();

        $entity->images()->detach($imageIds);

        // If primary was removed, set next image as primary
        if ($primaryRemoved) {
            $nextImage = $entity->images()->orderBy('sj_image_entity.sort_order')->first();
            if ($nextImage) {
                $entity->images()->updateExistingPivot($nextImage->id, ['is_primary' => true]);
            }
        }

        return $this->respondWithImages($entity, 'Bilder entfernt.');
    }

    private function handleSetPrimary(SjEntity $entity, array $arguments): ToolResult
    {
        $primaryImageId = $arguments['primary_image_id'] ?? null;
        if (!$primaryImageId) {
            return ToolResult::error('VALIDATION_ERROR', 'primary_image_id ist erforderlich für set_primary.');
        }

        // Verify image is attached to entity
        if (!$entity->images()->where('sj_images.id', $primaryImageId)->exists()) {
            return ToolResult::error('VALIDATION_ERROR', 'Bild ist nicht mit dieser Entity verknüpft.');
        }

        // Reset all to non-primary
        $entity->images()->each(function ($img) use ($entity) {
            $entity->images()->updateExistingPivot($img->id, ['is_primary' => false]);
        });

        // Set new primary
        $entity->images()->updateExistingPivot($primaryImageId, ['is_primary' => true]);

        return $this->respondWithImages($entity, 'Hauptbild gesetzt.');
    }

    private function handleReorder(SjEntity $entity, array $arguments): ToolResult
    {
        $imageIds = $arguments['image_ids'] ?? [];
        if (empty($imageIds)) {
            return ToolResult::error('VALIDATION_ERROR', 'image_ids ist erforderlich für reorder.');
        }

        foreach ($imageIds as $index => $imageId) {
            $entity->images()->updateExistingPivot($imageId, ['sort_order' => $index]);
        }

        return $this->respondWithImages($entity, 'Reihenfolge aktualisiert.');
    }

    private function respondWithImages(SjEntity $entity, string $message): ToolResult
    {
        $entity->load(['images' => fn($q) => $q->with('contextFile')->orderBy('sj_image_entity.sort_order')]);

        $images = $entity->images->map(fn($img) => [
            'id' => $img->id,
            'title' => $img->title,
            'thumbnail_url' => $img->thumbnail_url,
            'is_primary' => (bool) $img->pivot->is_primary,
            'sort_order' => $img->pivot->sort_order,
        ])->toArray();

        return ToolResult::success([
            'images' => $images,
            'message' => $message,
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['syltjunkie', 'images', 'entities', 'manage'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
