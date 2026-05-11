<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Platform\Syltjunkie\Models\SjUser;

class UserIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterStatus = '';
    public string $filterLevel = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterLevel(): void
    {
        $this->resetPage();
    }

    public function block(int $userId): void
    {
        $team = Auth::user()->currentTeam;
        $user = SjUser::where('team_id', $team->id)->findOrFail($userId);

        $user->update(['status' => 'blocked']);
    }

    public function activate(int $userId): void
    {
        $team = Auth::user()->currentTeam;
        $user = SjUser::where('team_id', $team->id)->findOrFail($userId);

        $user->update(['status' => 'active']);
    }

    public function delete(int $userId): void
    {
        $team = Auth::user()->currentTeam;
        $user = SjUser::where('team_id', $team->id)->findOrFail($userId);

        $user->delete();
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $query = SjUser::where('team_id', $team->id);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('email', 'like', "%{$this->search}%")
                  ->orWhere('name', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterLevel) {
            $query->where('current_level', $this->filterLevel);
        }

        $query->orderBy('created_at', 'desc');

        $users = $query->paginate(50);

        $levels = config('syltjunkie.gamification.levels', []);

        return view('syltjunkie::livewire.user-index', [
            'users' => $users,
            'levels' => $levels,
        ])->layout('platform::layouts.app');
    }
}
