<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityTypeGroup;

class EntityIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $filterGroupId = null;
    public ?int $filterTypeId = null;
    public string $filterStatus = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $query = SjEntity::where('team_id', $team->id)
            ->with('entityType.group');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('ort', 'like', "%{$this->search}%")
                  ->orWhere('slug', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterGroupId) {
            $query->whereHas('entityType', function ($q) {
                $q->where('group_id', $this->filterGroupId);
            });
        }

        if ($this->filterTypeId) {
            $query->where('entity_type_id', $this->filterTypeId);
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        $entities = $query->orderBy('name')->paginate(25);

        $groups = SjEntityTypeGroup::where('team_id', $team->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('syltjunkie::livewire.entity-index', [
            'entities' => $entities,
            'groups' => $groups,
        ])->layout('platform::layouts.app');
    }
}
