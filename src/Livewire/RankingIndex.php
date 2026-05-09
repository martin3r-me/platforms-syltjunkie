<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Platform\Syltjunkie\Models\SjKeywordRanking;

class RankingIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterDevice = '';
    public string $sortField = 'position';
    public string $sortDir = 'asc';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = $field === 'position' ? 'asc' : 'desc';
        }
        $this->resetPage();
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $query = SjKeywordRanking::query()
            ->whereHas('entityUrl', function ($q) use ($team) {
                $q->where('team_id', $team->id);
            })
            ->with([
                'keyword:id,keyword,search_volume',
                'entityUrl:id,entity_id,url,platform',
                'entityUrl.entity:id,name',
            ]);

        if ($this->search) {
            $query->whereHas('keyword', function ($q) {
                $q->where('keyword', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterDevice) {
            $query->where('device', $this->filterDevice);
        }

        if (in_array($this->sortField, ['position', 'captured_at'])) {
            $query->orderBy($this->sortField, $this->sortDir);
        } else {
            $query->orderBy('position', 'asc');
        }

        $rankings = $query->paginate(50);

        return view('syltjunkie::livewire.ranking-index', [
            'rankings' => $rankings,
        ])->layout('platform::layouts.app');
    }
}
