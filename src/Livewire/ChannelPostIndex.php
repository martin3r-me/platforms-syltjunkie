<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Platform\Syltjunkie\Models\SjChannel;
use Platform\Syltjunkie\Models\SjChannelPost;
use Platform\Syltjunkie\Services\SjPublishingService;

class ChannelPostIndex extends Component
{
    use WithPagination;

    public string $filterStatus = '';
    public string $filterChannel = '';

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterChannel(): void
    {
        $this->resetPage();
    }

    public function publishPost(int $id): void
    {
        $team = Auth::user()->currentTeam;
        $post = SjChannelPost::where('team_id', $team->id)->findOrFail($id);

        $service = app(SjPublishingService::class);
        $result = $service->publish($post);

        if (!$result['success']) {
            session()->flash('error', $result['error']);
        } else {
            session()->flash('success', 'Post erfolgreich veröffentlicht.');
        }
    }

    public function retryPost(int $id): void
    {
        $this->publishPost($id);
    }

    public function deletePost(int $id): void
    {
        $team = Auth::user()->currentTeam;
        $post = SjChannelPost::where('team_id', $team->id)->findOrFail($id);
        $post->delete();
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $query = SjChannelPost::where('team_id', $team->id)
            ->with(['channel', 'entity', 'images.contextFile.variants']);

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterChannel) {
            $query->where('channel_id', $this->filterChannel);
        }

        $posts = $query->orderByDesc('created_at')->paginate(20);

        $channels = SjChannel::where('team_id', $team->id)->orderBy('name')->get();

        return view('syltjunkie::livewire.channel-post-index', [
            'posts' => $posts,
            'channels' => $channels,
        ])->layout('platform::layouts.app');
    }
}
