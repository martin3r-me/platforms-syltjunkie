<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Syltjunkie\Models\SjEntity;

class EntityDetail extends Component
{
    public SjEntity $entity;

    public function mount(SjEntity $entity): void
    {
        abort_unless($entity->team_id === Auth::user()->currentTeam->id, 403);
        $this->entity = $entity;
    }

    public function render()
    {
        $this->entity->load([
            'entityType.group',
            'outgoingRelationships.targetEntity.entityType',
            'outgoingRelationships.relationType',
            'incomingRelationships.sourceEntity.entityType',
            'incomingRelationships.relationType',
        ]);

        return view('syltjunkie::livewire.entity-detail', [
            'entity' => $this->entity,
        ])->layout('platform::layouts.app');
    }
}
