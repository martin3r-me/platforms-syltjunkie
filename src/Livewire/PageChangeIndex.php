<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Platform\Syltjunkie\Models\SjPageChange;

class PageChangeIndex extends Component
{
    use WithPagination;

    public string $filterSeverity = '';
    public string $filterType = '';

    public function updatingFilterSeverity(): void
    {
        $this->resetPage();
    }

    public function updatingFilterType(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $query = SjPageChange::where('team_id', $team->id)
            ->with(['entityUrl:id,entity_id,url,platform', 'entityUrl.entity:id,name'])
            ->orderByDesc('detected_at');

        if ($this->filterSeverity) {
            $query->where('severity', $this->filterSeverity);
        }

        if ($this->filterType) {
            $query->where('change_type', $this->filterType);
        }

        $changes = $query->paginate(50);

        return view('syltjunkie::livewire.page-change-index', [
            'changes' => $changes,
        ])->layout('platform::layouts.app');
    }
}
