<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Platform\Syltjunkie\Models\SjContentPiece;

class ContentPieceIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterStatus = '';
    public string $filterType = '';
    public string $sortField = 'updated_at';
    public string $sortDir = 'desc';

    public function updatingSearch(): void
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

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = $field === 'title' ? 'asc' : 'desc';
        }
        $this->resetPage();
    }

    public function deleteContentPiece(int $id): void
    {
        $team = Auth::user()->currentTeam;
        $piece = SjContentPiece::where('team_id', $team->id)->findOrFail($id);
        $piece->delete();
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $query = SjContentPiece::where('team_id', $team->id)
            ->with(['keywords' => fn($q) => $q->wherePivot('is_primary', true), 'entities', 'coverImage.contextFile'])
            ->withCount(['keywords', 'entities', 'channelPosts']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                  ->orWhere('brief_notes', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterType) {
            $query->where('content_type', $this->filterType);
        }

        $allowedSorts = ['title', 'status', 'content_type', 'updated_at', 'published_at'];
        $sortField = in_array($this->sortField, $allowedSorts) ? $this->sortField : 'updated_at';
        $query->orderBy($sortField, $this->sortDir);

        $contentPieces = $query->paginate(30);

        $stats = [
            'total' => SjContentPiece::where('team_id', $team->id)->count(),
            'briefs' => SjContentPiece::where('team_id', $team->id)->where('status', 'brief')->count(),
            'drafts' => SjContentPiece::where('team_id', $team->id)->where('status', 'draft')->count(),
            'published' => SjContentPiece::where('team_id', $team->id)->where('status', 'published')->count(),
        ];

        return view('syltjunkie::livewire.content-piece-index', [
            'contentPieces' => $contentPieces,
            'stats' => $stats,
        ])->layout('platform::layouts.app');
    }
}
