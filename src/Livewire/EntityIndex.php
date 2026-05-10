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
    public string $sortField = 'name';
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
            $this->sortDir = $field === 'name' ? 'asc' : 'desc';
        }
        $this->resetPage();
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $query = SjEntity::where('team_id', $team->id)
            ->with([
                'entityType.group',
                'entityTypes',
                'entityUrls' => fn($q) => $q->where('is_active', true)->where('platform', 'website'),
                'entityUrls.latestSnapshot',
                'entityUrls.latestPageSnapshot',
                'outgoingRelationships' => fn($q) => $q->where('relation_type_id', 1)->where('is_active', true),
                'outgoingRelationships.targetEntity:id,name,slug',
            ])
            ->withCount(['contentBlocks' => fn($q) => $q->where('is_active', true)]);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('slug', 'like', "%{$this->search}%")
                  ->orWhereHas('outgoingRelationships', fn ($rq) => $rq
                      ->where('relation_type_id', 1)
                      ->where('is_active', true)
                      ->whereHas('targetEntity', fn ($tq) => $tq->where('name', 'like', "%{$this->search}%"))
                  );
            });
        }

        if ($this->filterGroupId) {
            $query->whereHas('entityType', function ($q) {
                $q->where('group_id', $this->filterGroupId);
            });
        }

        if ($this->filterTypeId) {
            $query->whereHas('entityTypes', fn ($q) => $q->where('sj_entity_types.id', $this->filterTypeId));
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        // Sortierung
        if (in_array($this->sortField, ['name', 'status'])) {
            $query->orderBy($this->sortField, $this->sortDir);
        } else {
            // Für berechnete Felder: Standard-Sortierung, Post-Sort im View
            $query->orderBy('name', 'asc');
        }

        $entities = $query->paginate(50);

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
