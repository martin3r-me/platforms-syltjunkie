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
            ->with('entityType:id,name,group_id');

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
                'ort' => $e->ort,
                'lat' => (float) $e->latitude,
                'lng' => (float) $e->longitude,
                'type' => $e->entityType?->name,
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
