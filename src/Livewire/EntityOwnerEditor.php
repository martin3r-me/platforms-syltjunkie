<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Platform\Syltjunkie\Mail\SjMagicLinkMail;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityOwner;

class EntityOwnerEditor extends Component
{
    public SjEntityOwner $owner;

    public ?int $entityId = null;
    public string $status = 'pending';
    public string $notes = '';
    public string $entitySearch = '';

    public function mount(int $owner): void
    {
        $team = Auth::user()->currentTeam;
        $this->owner = SjEntityOwner::where('team_id', $team->id)->findOrFail($owner);

        $this->entityId = $this->owner->entity_id;
        $this->status = $this->owner->status;
        $this->notes = $this->owner->notes ?? '';
    }

    public function save(): void
    {
        $wasNotApproved = $this->owner->status !== 'approved';

        $this->owner->update([
            'entity_id' => $this->entityId,
            'status' => $this->status,
            'notes' => $this->notes ?: null,
            'approved_at' => ($this->status === 'approved' && $wasNotApproved) ? now() : $this->owner->approved_at,
            'approved_by' => ($this->status === 'approved' && $wasNotApproved) ? Auth::id() : $this->owner->approved_by,
        ]);

        // Bei Freigabe automatisch Magic Link senden
        if ($this->status === 'approved' && $wasNotApproved) {
            $token = $this->owner->generateToken();
            Mail::to($this->owner->email)->send(new SjMagicLinkMail($this->owner, $token));
            session()->flash('success', 'Inhaber freigegeben und Magic Link gesendet.');
        } else {
            session()->flash('success', 'Inhaber gespeichert.');
        }
    }

    public function sendMagicLink(): void
    {
        if ($this->owner->status !== 'approved') {
            session()->flash('error', 'Inhaber muss freigegeben sein.');
            return;
        }

        $token = $this->owner->generateToken();
        Mail::to($this->owner->email)->send(new SjMagicLinkMail($this->owner, $token));

        session()->flash('success', 'Magic Link gesendet.');
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $entities = collect();
        if ($this->entitySearch) {
            $entities = SjEntity::where('team_id', $team->id)
                ->where('name', 'like', "%{$this->entitySearch}%")
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name', 'slug']);
        }

        return view('syltjunkie::livewire.entity-owner-editor', [
            'entities' => $entities,
            'currentEntity' => $this->entityId ? SjEntity::find($this->entityId, ['id', 'name', 'slug']) : null,
        ])->layout('platform::layouts.app');
    }
}
