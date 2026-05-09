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
}
