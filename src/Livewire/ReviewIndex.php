<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Syltjunkie\Models\SjEntity;

class ReviewIndex extends Component
{
    public string $sortField = 'rating';
    public string $sortDir = 'desc';

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = 'desc';
        }
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $entities = SjEntity::where('team_id', $team->id)
            ->where('is_active', true)
            ->with([
                'entityType:id,name',
                'entityUrls' => function ($q) {
                    $q->where('platform', 'google_maps')->where('is_active', true);
                },
                'entityUrls.latestSnapshot',
                'outgoingRelationships' => fn($q) => $q->where('relation_type_id', 1)->where('is_active', true),
                'outgoingRelationships.targetEntity:id,name,slug',
            ])
            ->get()
            ->map(function ($entity) {
                $googleUrl = $entity->entityUrls->first();
                $snapshot = $googleUrl?->latestSnapshot;
                $entity->latest_rating = $snapshot?->average_rating;
                $entity->latest_review_count = $snapshot?->review_count;
                $entity->snapshot_date = $snapshot?->captured_at;
                return $entity;
            })
            ->filter(function ($entity) {
                return $entity->latest_rating !== null || $entity->latest_review_count !== null;
            })
            ->sortByDesc(function ($entity) {
                if ($this->sortField === 'reviews') {
                    return $this->sortDir === 'desc' ? $entity->latest_review_count : -$entity->latest_review_count;
                }
                return $this->sortDir === 'desc' ? $entity->latest_rating : -$entity->latest_rating;
            })
            ->values();

        $avgRating = $entities->avg('latest_rating');
        $totalReviews = $entities->sum('latest_review_count');

        return view('syltjunkie::livewire.review-index', [
            'entities' => $entities,
            'avgRating' => $avgRating,
            'totalReviews' => $totalReviews,
        ])->layout('platform::layouts.app');
    }
}
