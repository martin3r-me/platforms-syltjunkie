<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Syltjunkie\Models\SjChannel;
use Platform\Syltjunkie\Models\SjChannelPost;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjImage;
use Platform\Syltjunkie\Services\SjPublishingService;

class ChannelPostComposer extends Component
{
    public ?int $channelId = null;
    public ?int $entityId = null;
    public string $postType = 'image';
    public string $caption = '';
    public string $hashtagsInput = '';
    public array $selectedImageIds = [];
    public ?string $scheduledAt = null;
    public bool $publishNow = true;

    public string $imageSearch = '';

    public function mount(): void
    {
        // Pre-fill from query params
        if (request()->has('channel_id')) {
            $this->channelId = (int) request('channel_id');
        }
        if (request()->has('entity_id')) {
            $this->entityId = (int) request('entity_id');
            $this->prefillFromEntity();
        }
    }

    public function updatedEntityId(): void
    {
        $this->prefillFromEntity();
    }

    private function prefillFromEntity(): void
    {
        if (!$this->entityId) {
            return;
        }

        $team = Auth::user()->currentTeam;
        $entity = SjEntity::where('team_id', $team->id)->find($this->entityId);
        if (!$entity) {
            return;
        }

        // Suggest hashtags from entity keywords
        if (!$this->hashtagsInput) {
            $keywords = $entity->keywords()->limit(5)->pluck('keyword')->toArray();
            $this->hashtagsInput = implode(', ', array_map(fn($k) => '#' . str_replace(' ', '', $k), $keywords));
        }
    }

    public function toggleImage(int $imageId): void
    {
        if (in_array($imageId, $this->selectedImageIds)) {
            $this->selectedImageIds = array_values(array_diff($this->selectedImageIds, [$imageId]));
        } else {
            $this->selectedImageIds[] = $imageId;
        }

        // Auto-switch post type
        if (count($this->selectedImageIds) > 1) {
            $this->postType = 'carousel';
        } elseif (count($this->selectedImageIds) === 1 && $this->postType === 'carousel') {
            $this->postType = 'image';
        }
    }

    public function saveDraft(): void
    {
        $this->savePost('draft');
    }

    public function schedulePost(): void
    {
        $this->savePost('scheduled');
    }

    public function publishPost(): void
    {
        $post = $this->savePost('draft');
        if (!$post) {
            return;
        }

        $service = app(SjPublishingService::class);
        $result = $service->publish($post);

        if (!$result['success']) {
            session()->flash('error', $result['error']);
        } else {
            session()->flash('success', 'Post erfolgreich veröffentlicht.');
        }
    }

    private function savePost(string $status): ?SjChannelPost
    {
        $this->validate([
            'channelId' => 'required|integer',
            'caption' => 'required|string|min:1',
        ]);

        $team = Auth::user()->currentTeam;

        $channel = SjChannel::where('team_id', $team->id)->findOrFail($this->channelId);

        $hashtags = $this->hashtagsInput
            ? array_map('trim', explode(',', $this->hashtagsInput))
            : null;

        $post = SjChannelPost::create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'entity_id' => $this->entityId,
            'post_type' => $this->postType,
            'status' => $status,
            'caption' => $this->caption,
            'hashtags' => $hashtags,
            'scheduled_at' => $status === 'scheduled' && $this->scheduledAt ? $this->scheduledAt : null,
            'created_by' => Auth::id(),
        ]);

        // Attach images
        foreach ($this->selectedImageIds as $index => $imageId) {
            $post->images()->attach($imageId, [
                'sort_order' => $index,
                'role' => 'media',
            ]);
        }

        return $post;
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $channels = SjChannel::where('team_id', $team->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $entities = SjEntity::where('team_id', $team->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(100)
            ->get();

        // Image picker: entity images first, then all
        $imageQuery = SjImage::where('team_id', $team->id)
            ->with('contextFile.variants');

        if ($this->imageSearch) {
            $imageQuery->where(function ($q) {
                $q->where('title', 'like', "%{$this->imageSearch}%")
                  ->orWhere('description', 'like', "%{$this->imageSearch}%");
            });
        }

        if ($this->entityId) {
            $imageQuery->orderByRaw('CASE WHEN id IN (SELECT sj_image_id FROM sj_image_entity WHERE entity_id = ?) THEN 0 ELSE 1 END', [$this->entityId]);
        }

        $images = $imageQuery->orderByDesc('created_at')->limit(48)->get();

        return view('syltjunkie::livewire.channel-post-composer', [
            'channels' => $channels,
            'entities' => $entities,
            'images' => $images,
        ])->layout('platform::layouts.app');
    }
}
