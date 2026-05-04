<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjKeywordRanking;
use Platform\Syltjunkie\Models\SjPageChange;

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
            'entityUrls' => fn($q) => $q->where('is_active', true)->orderByDesc('is_primary')->orderBy('platform'),
            'entityUrls.latestSnapshot',
            'entityUrls.latestPageSnapshot',
        ]);

        // Keyword-Rankings für alle Entity-URLs (latest per keyword)
        $entityUrlIds = $this->entity->entityUrls->pluck('id');
        $keywordRankings = collect();
        if ($entityUrlIds->isNotEmpty()) {
            $keywordRankings = SjKeywordRanking::whereIn('entity_url_id', $entityUrlIds)
                ->with('keyword:id,keyword,search_volume,cpc_cents,keyword_difficulty')
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

        return view('syltjunkie::livewire.entity-detail', [
            'entity' => $this->entity,
            'keywordRankings' => $keywordRankings,
            'recentChanges' => $recentChanges,
        ])->layout('platform::layouts.app');
    }
}
