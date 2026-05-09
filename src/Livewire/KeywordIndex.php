<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Platform\Syltjunkie\Models\SjKeyword;

class KeywordIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterIntent = '';
    public string $filterTopic = '';
    public ?int $volumeMin = null;
    public ?int $volumeMax = null;
    public string $sortField = 'search_volume';
    public string $sortDir = 'desc';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterIntent(): void
    {
        $this->resetPage();
    }

    public function updatingFilterTopic(): void
    {
        $this->resetPage();
    }

    public function updatingVolumeMin(): void
    {
        $this->resetPage();
    }

    public function updatingVolumeMax(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = $field === 'keyword' ? 'asc' : 'desc';
        }
        $this->resetPage();
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $query = SjKeyword::where('team_id', $team->id);

        if ($this->search) {
            $query->where('keyword', 'like', "%{$this->search}%");
        }

        if ($this->filterIntent) {
            $query->where('search_intent', $this->filterIntent);
        }

        if ($this->filterTopic) {
            $query->where('topic', $this->filterTopic);
        }

        if ($this->volumeMin !== null && $this->volumeMin > 0) {
            $query->where('search_volume', '>=', $this->volumeMin);
        }

        if ($this->volumeMax !== null && $this->volumeMax > 0) {
            $query->where('search_volume', '<=', $this->volumeMax);
        }

        $allowedSorts = ['keyword', 'search_volume', 'keyword_difficulty', 'cpc_cents', 'trends_average_interest', 'trends_fetched_at'];
        $sortField = in_array($this->sortField, $allowedSorts) ? $this->sortField : 'search_volume';
        $query->orderBy($sortField, $this->sortDir);

        $keywords = $query->paginate(50);

        $stats = [
            'total' => SjKeyword::where('team_id', $team->id)->count(),
            'with_trends' => SjKeyword::where('team_id', $team->id)->whereNotNull('trends_fetched_at')->count(),
            'avg_volume' => (int) SjKeyword::where('team_id', $team->id)->avg('search_volume'),
        ];

        $intents = SjKeyword::where('team_id', $team->id)
            ->whereNotNull('search_intent')
            ->distinct()
            ->pluck('search_intent')
            ->sort()
            ->values();

        $topics = SjKeyword::where('team_id', $team->id)
            ->whereNotNull('topic')
            ->distinct()
            ->pluck('topic')
            ->sort()
            ->values();

        return view('syltjunkie::livewire.keyword-index', [
            'keywords' => $keywords,
            'stats' => $stats,
            'intents' => $intents,
            'topics' => $topics,
        ])->layout('platform::layouts.app');
    }
}
