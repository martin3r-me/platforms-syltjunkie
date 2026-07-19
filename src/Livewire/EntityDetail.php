<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Core\Services\ContextFileService;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityType;
use Platform\Syltjunkie\Models\SjImage;
use Platform\Syltjunkie\Models\SjKeywordRanking;
use Platform\Syltjunkie\Models\SjPageChange;
use Platform\Syltjunkie\Models\SjChannelPost;
use Platform\Syltjunkie\Models\SjTrendSignal;
use Platform\Syltjunkie\Models\SjWeather;

class EntityDetail extends Component
{
    use WithFileUploads;
    public SjEntity $entity;

    public ?array $geometry = null;
    public ?float $editLatitude = null;
    public ?float $editLongitude = null;
    public $imageUpload;

    public bool $showTypeEditor = false;
    public array $selectedTypeIds = [];
    public ?int $primaryTypeId = null;
    public string $typeSearch = '';

    public function mount(SjEntity $entity): void
    {
        abort_unless($entity->team_id === Auth::user()->currentTeam->id, 403);
        $this->entity = $entity;
        $this->geometry = $entity->getGeometryGeoJson();
        $this->editLatitude = $entity->latitude ? (float) $entity->latitude : null;
        $this->editLongitude = $entity->longitude ? (float) $entity->longitude : null;

        $this->selectedTypeIds = $entity->entityTypes()->pluck('sj_entity_types.id')->toArray();
        $this->primaryTypeId = $entity->entity_type_id;
    }

    public function saveGeometry(?array $geometry): void
    {
        $this->entity->setGeometry($geometry);
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

    public function toggleEntityType(int $typeId): void
    {
        if (in_array($typeId, $this->selectedTypeIds)) {
            $this->selectedTypeIds = array_values(array_diff($this->selectedTypeIds, [$typeId]));
            if ($this->primaryTypeId === $typeId) {
                $this->primaryTypeId = $this->selectedTypeIds[0] ?? null;
            }
        } else {
            $this->selectedTypeIds[] = $typeId;
            if ($this->primaryTypeId === null) {
                $this->primaryTypeId = $typeId;
            }
        }
    }

    public function setPrimaryEntityType(int $typeId): void
    {
        if (in_array($typeId, $this->selectedTypeIds)) {
            $this->primaryTypeId = $typeId;
        }
    }

    public function saveEntityTypes(): void
    {
        if (empty($this->selectedTypeIds)) {
            return;
        }

        $this->entity->syncEntityTypes($this->selectedTypeIds, $this->primaryTypeId);
        $this->entity->load('entityTypes', 'entityType.group');
        $this->showTypeEditor = false;
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
            'entityTypes',
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

        // Zentrale SEO-Signale (Keystone) — nach sj_entity_url-ID, guarded.
        $seoSignals = [];
        if ($entityUrlIds->isNotEmpty() && app()->bound(\Platform\Core\Contracts\SeoSignalServiceInterface::class)) {
            $seoSignals = app(\Platform\Core\Contracts\SeoSignalServiceInterface::class)
                ->getSignalsBySource((int) $this->entity->team_id, 'syltjunkie', $entityUrlIds->all());
        }

        $entityImages = $this->entity->images()
            ->with('contextFile.variants')
            ->get();

        $entityPosts = SjChannelPost::where('entity_id', $this->entity->id)
            ->with(['channel', 'images.contextFile.variants'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $currentWeather = SjWeather::forEntity($this->entity->id)
            ->current()
            ->where('date', today())
            ->first();

        $weatherForecast = SjWeather::forEntity($this->entity->id)
            ->forecast()
            ->orderBy('date')
            ->get();

        // Available entity types for type editor
        $availableTypes = collect();
        if ($this->showTypeEditor) {
            $typeQuery = SjEntityType::where('team_id', Auth::user()->currentTeam->id)
                ->with('group:id,name,code')
                ->orderBy('name');
            if ($this->typeSearch) {
                $typeQuery->where('name', 'like', "%{$this->typeSearch}%");
            }
            $availableTypes = $typeQuery->get();
        }

        return view('syltjunkie::livewire.entity-detail', [
            'entity' => $this->entity,
            'keywordRankings' => $keywordRankings,
            'recentChanges' => $recentChanges,
            'entitySignals' => $entitySignals,
            'seoSignals' => $seoSignals,
            'entityImages' => $entityImages,
            'entityPosts' => $entityPosts,
            'currentWeather' => $currentWeather,
            'weatherForecast' => $weatherForecast,
            'availableTypes' => $availableTypes,
        ])->layout('platform::layouts.app');
    }
}
