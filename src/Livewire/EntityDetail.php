<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Core\Services\ContextFileService;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjImage;
use Platform\Syltjunkie\Models\SjKeywordRanking;
use Platform\Syltjunkie\Models\SjPageChange;
use Platform\Syltjunkie\Models\SjTrendSignal;

class EntityDetail extends Component
{
    use WithFileUploads;
    public SjEntity $entity;

    public ?array $geometry = null;
    public ?float $editLatitude = null;
    public ?float $editLongitude = null;
    public $imageUpload;

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

    public function uploadEntityImage(): void
    {
        $this->validate([
            'imageUpload' => 'required|image|max:20480',
        ]);

        $team = Auth::user()->currentTeam;
        $service = app(ContextFileService::class);

        $result = $service->uploadForContext(
            $this->imageUpload,
            SjImage::class,
            0,
            [
                'team_id' => $team->id,
                'user_id' => Auth::id(),
                'folder' => 'syltjunkie',
                'generate_variants' => true,
            ]
        );

        $contextFile = \Platform\Core\Models\ContextFile::find($result['id']);
        $meta = $contextFile->meta ?? [];

        $image = SjImage::create([
            'team_id' => $team->id,
            'context_file_id' => $result['id'],
            'latitude' => $meta['gps']['latitude'] ?? null,
            'longitude' => $meta['gps']['longitude'] ?? null,
            'title' => pathinfo($this->imageUpload->getClientOriginalName(), PATHINFO_FILENAME),
        ]);

        $this->entity->images()->attach($image->id, [
            'sort_order' => $this->entity->images()->count(),
            'is_primary' => !$this->entity->images()->wherePivot('is_primary', true)->exists(),
        ]);

        $this->imageUpload = null;
    }

    public function unlinkImage(int $imageId): void
    {
        $this->entity->images()->detach($imageId);
    }

    public function setPrimaryImage(int $imageId): void
    {
        // Reset all to non-primary
        $this->entity->images()->updateExistingPivot(
            $this->entity->images->pluck('id')->toArray(),
            ['is_primary' => false]
        );

        // Set the selected one as primary
        $this->entity->images()->updateExistingPivot($imageId, ['is_primary' => true]);
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

        $entityImages = $this->entity->images()
            ->with('contextFile.variants')
            ->get();

        return view('syltjunkie::livewire.entity-detail', [
            'entity' => $this->entity,
            'keywordRankings' => $keywordRankings,
            'recentChanges' => $recentChanges,
            'entitySignals' => $entitySignals,
            'entityImages' => $entityImages,
        ])->layout('platform::layouts.app');
    }
}
