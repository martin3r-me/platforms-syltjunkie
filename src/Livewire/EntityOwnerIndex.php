<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\WithPagination;
use Platform\Syltjunkie\Mail\SjMagicLinkMail;
use Platform\Syltjunkie\Models\SjEntityOwner;

class EntityOwnerIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterStatus = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function approve(int $ownerId): void
    {
        $team = Auth::user()->currentTeam;
        $owner = SjEntityOwner::where('team_id', $team->id)->findOrFail($ownerId);

        $owner->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);

        // Magic Link direkt senden
        $token = $owner->generateToken();
        Mail::to($owner->email)->send(new SjMagicLinkMail($owner, $token));
    }

    public function block(int $ownerId): void
    {
        $team = Auth::user()->currentTeam;
        $owner = SjEntityOwner::where('team_id', $team->id)->findOrFail($ownerId);

        $owner->update(['status' => 'blocked']);
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $query = SjEntityOwner::where('team_id', $team->id)
            ->with(['entity:id,name,slug', 'approvedBy:id,name']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('email', 'like', "%{$this->search}%")
                  ->orWhere('name', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        $query->orderByRaw("FIELD(status, 'pending', 'approved', 'blocked')")
            ->orderBy('created_at', 'desc');

        $owners = $query->paginate(50);

        return view('syltjunkie::livewire.entity-owner-index', [
            'owners' => $owners,
        ])->layout('platform::layouts.app');
    }
}
