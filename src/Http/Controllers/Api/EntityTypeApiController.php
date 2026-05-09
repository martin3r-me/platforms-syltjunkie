<?php

namespace Platform\Syltjunkie\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Platform\Core\Http\Controllers\ApiController;
use Platform\Syltjunkie\Http\Controllers\Api\Concerns\ResolvesPublicTeam;
use Platform\Syltjunkie\Models\SjEntityTypeGroup;

class EntityTypeApiController extends ApiController
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

        $data = $groups->map(fn ($group) => [
            'code' => $group->code,
            'name' => $group->name,
            'icon' => $group->icon,
            'types' => $group->entityTypes->map(fn ($type) => [
                'code' => $type->code,
                'name' => $type->name,
                'icon' => $type->icon,
                'color' => $type->color,
                'entity_count' => $type->entities_count,
            ])->values(),
        ])->values();

        return $this->success($data);
    }

    public function groups(Request $request): JsonResponse
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

        $data = $groups->map(function (SjEntityTypeGroup $group) {
            $types = $group->entityTypes->map(fn ($type) => [
                'code' => $type->code,
                'name' => $type->name,
                'icon' => $type->icon,
                'color' => $type->color,
                'entity_count' => $type->entities_count,
            ])->values();

            return [
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
                'entity_count' => $types->sum('entity_count'),
                'types' => $types,
            ];
        })->values();

        return $this->success($data);
    }
}
