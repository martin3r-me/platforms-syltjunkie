<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjKeywordRanking;
use Platform\Syltjunkie\Models\SjPageChange;
use Platform\Syltjunkie\Models\SjTrendSignal;

class EntityDetail extends Component
{
    public SjEntity $entity;

    public ?array $geometry = null;
    public ?float $editLatitude = null;
    public ?float $editLongitude = null;

    public function mount(SjEntity $entity): void
    {
        abort_unless($entity->team_id === Auth::user()->currentTeam->id, 403);
        $this->entity = $entity;
        $this->geometry = $entity->geometry;
        $this->editLatitude = $entity->latitude ? (float) $entity->latitude : null;
        $this->editLongitude = $entity->longitude ? (float) $entity->longitude : null;
    }

    public function saveGeometry(?array $geometry): void
    {
        $this->entity->update(['geometry' => $geometry]);
        $this->geometry = $geometry;
    }

    public function saveCoordinates(float $lat, float $lng): void
    {
        $this->entity->update([
            'latitude' => $lat,
            'longitude' => $lng,
        ]);
        $this->editLatitude = $lat;
        $this->editLongitude = $lng;
    }

    public function render()
    {
        $this->entity->load([
            'entityType.group',
            'outgoingRelationships.targetEntity.entityType',
            'outgoingRelationships.relationType',
            'incomingRelationships.sourceEntity.entityType',
            'incomingRelationships.relationType',
            'entityUrls' => fn($q) => $q->where('is_active', true)->orderByDesc('is_primary')->orderBy('platform'),
            'entityUrls.latestSnapshot',
            'entityUrls.latestPageSnapshot',
        ]);

        // Keyword-Rankings für alle Entity-URLs (latest per keyword)
        $entityUrlIds = $this->entity->entityUrls->pluck('id');
        $keywordRankings = collect();
        if ($entityUrlIds->isNotEmpty()) {
            $keywordRankings = SjKeywordRanking::whereIn('entity_url_id', $entityUrlIds)
                ->with('keyword:id,keyword,search_volume,cpc_cents,keyword_difficulty,monthly_volumes,peak_month,seasonality_index')
                ->orderBy('position')
                ->whereIn('id', function ($q) use ($entityUrlIds) {
                    $q->selectRaw('MAX(id)')
                        ->from('sj_keyword_rankings')
                        ->whereIn('entity_url_id', $entityUrlIds)
                        ->groupBy('keyword_id', 'entity_url_id');
                })
                ->limit(50)
                ->get();
        }

        // Recent page changes
        $recentChanges = collect();
        if ($entityUrlIds->isNotEmpty()) {
            $recentChanges = SjPageChange::whereIn('entity_url_id', $entityUrlIds)
                ->with('entityUrl:id,url')
                ->orderByDesc('detected_at')
                ->limit(20)
                ->get();
        }

        $entitySignals = SjTrendSignal::where('entity_id', $this->entity->id)
            ->whereIn('status', ['new', 'acknowledged'])
            ->orderByDesc('detected_at')
            ->limit(10)
            ->get();

        return view('syltjunkie::livewire.entity-detail', [
            'entity' => $this->entity,
            'keywordRankings' => $keywordRankings,
            'recentChanges' => $recentChanges,
            'entitySignals' => $entitySignals,
        ])->layout('platform::layouts.app');
    }
}
