<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Platform\Syltjunkie\Models\SjTrendSignal;

class TrendSignalIndex extends Component
{
    use WithPagination;

    public string $filterSeverity = '';
    public string $filterStatus = '';
    public string $filterType = '';

    public function updatingFilterSeverity(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterType(): void
    {
        $this->resetPage();
    }

    public function updateStatus(int $signalId, string $status): void
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $signal = SjTrendSignal::where('team_id', $team->id)->findOrFail($signalId);
        $signal->update(['status' => $status]);
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $query = SjTrendSignal::where('team_id', $team->id)
            ->with(['entity:id,name', 'keyword:id,keyword'])
            ->orderByDesc('detected_at');

        if ($this->filterSeverity) {
            $query->where('severity', $this->filterSeverity);
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        } else {
            $query->whereIn('status', ['new', 'acknowledged']);
        }

        if ($this->filterType) {
            $query->where('signal_type', $this->filterType);
        }

        $signals = $query->paginate(50);

        $stats = [
            'action' => SjTrendSignal::where('team_id', $team->id)->whereIn('status', ['new', 'acknowledged'])->where('severity', 'action')->count(),
            'watch' => SjTrendSignal::where('team_id', $team->id)->whereIn('status', ['new', 'acknowledged'])->where('severity', 'watch')->count(),
            'info' => SjTrendSignal::where('team_id', $team->id)->whereIn('status', ['new', 'acknowledged'])->where('severity', 'info')->count(),
        ];

        return view('syltjunkie::livewire.trend-signal-index', [
            'signals' => $signals,
            'stats' => $stats,
        ])->layout('platform::layouts.app');
    }
}
