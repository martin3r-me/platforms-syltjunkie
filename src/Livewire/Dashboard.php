<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityType;
use Platform\Syltjunkie\Models\SjEntityTypeGroup;
use Platform\Syltjunkie\Models\SjKeyword;
use Platform\Syltjunkie\Models\SjTrendSignal;

class Dashboard extends Component
{
    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $entityCount = SjEntity::where('team_id', $team->id)->count();
        $typeCount = SjEntityType::where('team_id', $team->id)->count();
        $groupCount = SjEntityTypeGroup::where('team_id', $team->id)->count();

        $recentEntities = SjEntity::where('team_id', $team->id)
            ->with('entityType.group')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $trendSignals = SjTrendSignal::where('team_id', $team->id)
            ->whereIn('status', ['new', 'acknowledged'])
            ->with('entity:id,name')
            ->orderByDesc('detected_at')
            ->limit(10)
            ->get();

        $mapEntities = SjEntity::where('team_id', $team->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('is_active', true)
            ->select('id', 'name', 'ort', 'latitude', 'longitude', 'status', 'entity_type_id')
            ->with('entityType:id,name,color')
            ->get();

        $keywordStats = [
            'total' => SjKeyword::where('team_id', $team->id)->count(),
            'high_volume' => SjKeyword::where('team_id', $team->id)->where('search_volume', '>=', 1000)->count(),
            'trending' => SjKeyword::where('team_id', $team->id)->where('trends_average_interest', '>=', 50)->count(),
        ];

        $mapPoints = $mapEntities->map(function ($e) {
            return [
                'id' => $e->id,
                'name' => $e->name,
                'ort' => $e->ort,
                'lat' => (float) $e->latitude,
                'lng' => (float) $e->longitude,
                'type' => $e->entityType?->name,
                'color' => $e->entityType?->color ?? '#3B82F6',
                'status' => $e->status,
            ];
        })->values()->toArray();

        return view('syltjunkie::livewire.dashboard', [
            'entityCount' => $entityCount,
            'typeCount' => $typeCount,
            'groupCount' => $groupCount,
            'recentEntities' => $recentEntities,
            'trendSignals' => $trendSignals,
            'mapEntities' => $mapEntities,
            'mapPoints' => $mapPoints,
            'keywordStats' => $keywordStats,
        ])->layout('platform::layouts.app');
    }
}
