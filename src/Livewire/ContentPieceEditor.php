<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Platform\Syltjunkie\Models\SjContentPiece;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjImage;
use Platform\Syltjunkie\Models\SjKeyword;

class ContentPieceEditor extends Component
{
    public ?int $contentPieceId = null;

    // Form fields
    public string $title = '';
    public string $slug = '';
    public string $contentType = 'guide';
    public string $status = 'brief';
    public string $briefNotes = '';
    public string $bodyMarkdown = '';
    public string $excerpt = '';
    public ?int $coverImageId = null;
    public string $seoTitle = '';
    public string $seoDescription = '';
    public ?int $targetTrafficEstimate = null;
    public ?int $targetValueCents = null;

    // Relations
    public array $selectedKeywordIds = [];
    public ?int $primaryKeywordId = null;
    public array $selectedEntityIds = [];
    public string $keywordSearch = '';
    public string $entitySearch = '';
    public string $imageSearch = '';

    public function mount(?int $contentPiece = null): void
    {
        // Support pre-fill from keyword brief
        if (request()->has('keyword_id')) {
            $this->prefillFromKeyword((int) request('keyword_id'));
        }

        if ($contentPiece) {
            $this->loadContentPiece($contentPiece);
        }
    }

    protected function loadContentPiece(int $id): void
    {
        $team = Auth::user()->currentTeam;
        $piece = SjContentPiece::where('team_id', $team->id)->findOrFail($id);

        $this->contentPieceId = $piece->id;
        $this->title = $piece->title ?? '';
        $this->slug = $piece->slug ?? '';
        $this->contentType = $piece->content_type ?? 'guide';
        $this->status = $piece->status ?? 'brief';
        $this->briefNotes = $piece->brief_notes ?? '';
        $this->bodyMarkdown = $piece->body_markdown ?? '';
        $this->excerpt = $piece->excerpt ?? '';
        $this->coverImageId = $piece->cover_image_id;
        $this->seoTitle = $piece->seo_title ?? '';
        $this->seoDescription = $piece->seo_description ?? '';
        $this->targetTrafficEstimate = $piece->target_traffic_estimate;
        $this->targetValueCents = $piece->target_value_cents;

        $this->selectedKeywordIds = $piece->keywords()->pluck('sj_keywords.id')->toArray();
        $this->primaryKeywordId = $piece->primaryKeyword()->first()?->id;
        $this->selectedEntityIds = $piece->entities()->pluck('sj_entities.id')->toArray();
    }

    protected function prefillFromKeyword(int $keywordId): void
    {
        $team = Auth::user()->currentTeam;
        $keyword = SjKeyword::where('team_id', $team->id)->find($keywordId);
        if (!$keyword) {
            return;
        }

        $this->selectedKeywordIds = [$keyword->id];
        $this->primaryKeywordId = $keyword->id;
        $this->title = ucfirst($keyword->keyword);
        $this->slug = Str::slug($keyword->keyword);
        $this->contentType = $this->guessContentType($keyword);

        // Brief notes with keyword data
        $notes = "Primary Keyword: {$keyword->keyword}\n";
        $notes .= "Search Volume: " . number_format($keyword->search_volume ?? 0) . "\n";
        if ($keyword->search_intent) {
            $notes .= "Intent: {$keyword->search_intent}\n";
        }
        if ($keyword->topic) {
            $notes .= "Topic: {$keyword->topic}\n";
        }
        if ($keyword->keyword_difficulty !== null) {
            $notes .= "KD: {$keyword->keyword_difficulty}\n";
        }
        $this->briefNotes = $notes;

        // Auto-suggest related entities via keyword-entity relevance
        $entityIds = $keyword->entities()->pluck('sj_entities.id')->toArray();
        $this->selectedEntityIds = array_slice($entityIds, 0, 10);

        $this->targetTrafficEstimate = $keyword->search_volume;
    }

    protected function guessContentType(SjKeyword $keyword): string
    {
        $intent = $keyword->search_intent;
        $topic = $keyword->topic;

        if ($intent === 'navigational') {
            return 'entity_page';
        }
        if ($intent === 'commercial' || $intent === 'transactional') {
            return 'listing_page';
        }
        if ($topic === 'events') {
            return 'event';
        }

        return 'guide';
    }

    public function updatedTitle(): void
    {
        if (!$this->contentPieceId && !$this->slug) {
            $this->slug = Str::slug($this->title);
        }
    }

    public function toggleKeyword(int $keywordId): void
    {
        if (in_array($keywordId, $this->selectedKeywordIds)) {
            $this->selectedKeywordIds = array_values(array_diff($this->selectedKeywordIds, [$keywordId]));
            if ($this->primaryKeywordId === $keywordId) {
                $this->primaryKeywordId = $this->selectedKeywordIds[0] ?? null;
            }
        } else {
            $this->selectedKeywordIds[] = $keywordId;
            if ($this->primaryKeywordId === null) {
                $this->primaryKeywordId = $keywordId;
            }
        }
    }

    public function setPrimaryKeyword(int $keywordId): void
    {
        if (in_array($keywordId, $this->selectedKeywordIds)) {
            $this->primaryKeywordId = $keywordId;
        }
    }

    public function toggleEntity(int $entityId): void
    {
        if (in_array($entityId, $this->selectedEntityIds)) {
            $this->selectedEntityIds = array_values(array_diff($this->selectedEntityIds, [$entityId]));
        } else {
            $this->selectedEntityIds[] = $entityId;
        }
    }

    public function setCoverImage(int $imageId): void
    {
        $this->coverImageId = $this->coverImageId === $imageId ? null : $imageId;
    }

    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'contentType' => 'required|string',
            'status' => 'required|string',
        ]);

        $team = Auth::user()->currentTeam;

        $data = [
            'title' => $this->title,
            'slug' => $this->slug,
            'content_type' => $this->contentType,
            'status' => $this->status,
            'brief_notes' => $this->briefNotes ?: null,
            'body_markdown' => $this->bodyMarkdown ?: null,
            'excerpt' => $this->excerpt ?: null,
            'cover_image_id' => $this->coverImageId,
            'seo_title' => $this->seoTitle ?: null,
            'seo_description' => $this->seoDescription ?: null,
            'target_traffic_estimate' => $this->targetTrafficEstimate,
            'target_value_cents' => $this->targetValueCents,
        ];

        if ($this->contentPieceId) {
            $piece = SjContentPiece::where('team_id', $team->id)->findOrFail($this->contentPieceId);
            $piece->update($data);
        } else {
            $data['team_id'] = $team->id;
            $piece = SjContentPiece::create($data);
            $this->contentPieceId = $piece->id;
        }

        // Sync keywords
        $keywordSync = [];
        foreach ($this->selectedKeywordIds as $kwId) {
            $keywordSync[$kwId] = ['is_primary' => $kwId === $this->primaryKeywordId];
        }
        $piece->keywords()->sync($keywordSync);

        // Sync entities
        $entitySync = [];
        foreach ($this->selectedEntityIds as $index => $entityId) {
            $entitySync[$entityId] = ['display_order' => $index];
        }
        $piece->entities()->sync($entitySync);

        if ($this->status === 'published' && !$piece->published_at) {
            $piece->update(['published_at' => now()]);
        }

        session()->flash('success', 'Content Piece gespeichert.');
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        // Available keywords
        $keywordQuery = SjKeyword::where('team_id', $team->id)
            ->orderByDesc('search_volume');
        if ($this->keywordSearch) {
            $keywordQuery->where('keyword', 'like', "%{$this->keywordSearch}%");
        }
        $availableKeywords = $keywordQuery->limit(30)->get();

        // Selected keywords (always show)
        $selectedKeywords = $this->selectedKeywordIds
            ? SjKeyword::whereIn('id', $this->selectedKeywordIds)->get()
            : collect();

        // Available entities
        $entityQuery = SjEntity::where('team_id', $team->id)
            ->where('is_active', true)
            ->with('entityType:id,name,color')
            ->orderBy('name');
        if ($this->entitySearch) {
            $entityQuery->where('name', 'like', "%{$this->entitySearch}%");
        }
        $availableEntities = $entityQuery->limit(30)->get();

        // Selected entities (always show)
        $selectedEntities = $this->selectedEntityIds
            ? SjEntity::whereIn('id', $this->selectedEntityIds)->with('entityType:id,name,color')->get()
            : collect();

        // Images for cover selection
        $imageQuery = SjImage::where('team_id', $team->id)
            ->with('contextFile');
        if ($this->imageSearch) {
            $imageQuery->where(function ($q) {
                $q->where('title', 'like', "%{$this->imageSearch}%")
                  ->orWhere('description', 'like', "%{$this->imageSearch}%");
            });
        }
        // Prioritize images from selected entities
        if ($this->selectedEntityIds) {
            $imageQuery->orderByRaw(
                'CASE WHEN id IN (SELECT sj_image_id FROM sj_image_entity WHERE entity_id IN (' .
                implode(',', array_map('intval', $this->selectedEntityIds)) .
                ')) THEN 0 ELSE 1 END'
            );
        }
        $images = $imageQuery->orderByDesc('created_at')->limit(24)->get();

        return view('syltjunkie::livewire.content-piece-editor', [
            'availableKeywords' => $availableKeywords,
            'selectedKeywords' => $selectedKeywords,
            'availableEntities' => $availableEntities,
            'selectedEntities' => $selectedEntities,
            'images' => $images,
        ])->layout('platform::layouts.app');
    }
}
