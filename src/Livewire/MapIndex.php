<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityTypeGroup;

class MapIndex extends Component
{
    public ?int $filterGroupId = null;

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $query = SjEntity::where('team_id', $team->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('is_active', true)
            ->with([
                'entityType:id,name,group_id,color',
                'outgoingRelationships' => fn($q) => $q->where('relation_type_id', 1)->where('is_active', true),
                'outgoingRelationships.targetEntity:id,name,slug',
            ]);

        if ($this->filterGroupId) {
            $query->whereHas('entityType', function ($q) {
                $q->where('group_id', $this->filterGroupId);
            });
        }

        $mapEntities = $query->get();

        $mapPoints = $mapEntities->map(function ($e) {
            return [
                'id' => $e->id,
                'name' => $e->name,
                'ort' => $e->outgoingRelationships->first()?->targetEntity?->name,
                'lat' => (float) $e->latitude,
                'lng' => (float) $e->longitude,
                'type' => $e->entityType?->name,
                'color' => $e->entityType?->color ?? '#3B82F6',
                'status' => $e->status,
            ];
        })->values()->toArray();

        $groups = SjEntityTypeGroup::where('team_id', $team->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('syltjunkie::livewire.map-index', [
            'mapEntities' => $mapEntities,
            'mapPoints' => $mapPoints,
            'groups' => $groups,
        ])->layout('platform::layouts.app');
    }
}
