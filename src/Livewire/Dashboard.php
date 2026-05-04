<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityType;
use Platform\Syltjunkie\Models\SjEntityTypeGroup;

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

        return view('syltjunkie::livewire.dashboard', [
            'entityCount' => $entityCount,
            'typeCount' => $typeCount,
            'groupCount' => $groupCount,
            'recentEntities' => $recentEntities,
        ])->layout('platform::layouts.app');
    }
}
