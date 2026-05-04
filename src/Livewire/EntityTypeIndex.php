<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Syltjunkie\Models\SjEntityTypeGroup;

class EntityTypeIndex extends Component
{
    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $groups = SjEntityTypeGroup::where('team_id', $team->id)
            ->where('is_active', true)
            ->with(['entityTypes' => function ($q) {
                $q->withCount('entities')->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        return view('syltjunkie::livewire.entity-type-index', [
            'groups' => $groups,
        ])->layout('platform::layouts.app');
    }
}
