<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Platform\Syltjunkie\Models\SjUser;
use Platform\Syltjunkie\Services\SjPointsService;

class UserDetail extends Component
{
    use WithPagination;

    public SjUser $user;
    public int $awardPoints = 0;
    public string $awardAction = 'manual';
    public string $awardNote = '';

    public function mount(SjUser $user): void
    {
        abort_unless($user->team_id === Auth::user()->currentTeam->id, 403);
        $this->user = $user;
    }

    public function block(): void
    {
        $this->user->update(['status' => 'blocked']);
        $this->user->refresh();
    }

    public function activate(): void
    {
        $this->user->update(['status' => 'active']);
        $this->user->refresh();
    }

    public function clearToken(): void
    {
        $this->user->clearToken();
        $this->user->refresh();
        session()->flash('success', 'Token zurückgesetzt.');
    }

    public function awardManualPoints(): void
    {
        if ($this->awardPoints === 0) {
            return;
        }

        $meta = $this->awardNote ? ['note' => $this->awardNote] : [];

        SjPointsService::award($this->user, $this->awardAction, $this->awardPoints, $meta);

        $this->user->refresh();
        $this->awardPoints = 0;
        $this->awardAction = 'manual';
        $this->awardNote = '';
        $this->resetPage();

        session()->flash('success', 'Punkte gebucht.');
    }

    public function recalculateBalance(): void
    {
        SjPointsService::recalculateBalance($this->user);
        $this->user->refresh();

        session()->flash('success', 'Balance neu berechnet.');
    }

    public function render()
    {
        $pointsHistory = $this->user->pointsHistory()->paginate(20);
        $levels = config('syltjunkie.gamification.levels', []);
        $currentLevel = $this->user->currentLevel();
        $nextLevel = $this->user->nextLevel();

        $progressPercent = 0;
        if ($nextLevel) {
            $range = $nextLevel['min_points'] - $currentLevel['min_points'];
            $progress = $this->user->points_balance - $currentLevel['min_points'];
            $progressPercent = $range > 0 ? min(100, round(($progress / $range) * 100)) : 100;
        } else {
            $progressPercent = 100;
        }

        return view('syltjunkie::livewire.user-detail', [
            'pointsHistory' => $pointsHistory,
            'levels' => $levels,
            'currentLevel' => $currentLevel,
            'nextLevel' => $nextLevel,
            'progressPercent' => $progressPercent,
        ])->layout('platform::layouts.app');
    }
}
