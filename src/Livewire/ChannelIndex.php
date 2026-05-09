<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationsInstagramAccount;
use Platform\Syltjunkie\Models\SjChannel;

class ChannelIndex extends Component
{
    public bool $showModal = false;
    public ?int $editingChannelId = null;

    public string $formName = '';
    public string $formType = 'instagram';
    public ?int $formIntegrationConnectionId = null;
    public ?int $formInstagramAccountId = null;
    public string $formDefaultHashtags = '';

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $team = Auth::user()->currentTeam;
        $channel = SjChannel::where('team_id', $team->id)->findOrFail($id);

        $this->editingChannelId = $channel->id;
        $this->formName = $channel->name;
        $this->formType = $channel->type;
        $this->formIntegrationConnectionId = $channel->integration_connection_id;
        $this->formInstagramAccountId = $channel->instagram_account_id;
        $this->formDefaultHashtags = implode(', ', $channel->config['default_hashtags'] ?? []);
        $this->showModal = true;
    }

    public function saveChannel(): void
    {
        $this->validate([
            'formName' => 'required|string|max:255',
            'formType' => 'required|string|max:50',
        ]);

        $team = Auth::user()->currentTeam;

        $config = [];
        if ($this->formIntegrationConnectionId) {
            $config['integration_connection_id'] = $this->formIntegrationConnectionId;
        }
        if ($this->formInstagramAccountId) {
            $config['instagram_account_id'] = $this->formInstagramAccountId;
        }
        if ($this->formDefaultHashtags) {
            $config['default_hashtags'] = array_map('trim', explode(',', $this->formDefaultHashtags));
        }

        if ($this->editingChannelId) {
            $channel = SjChannel::where('team_id', $team->id)->findOrFail($this->editingChannelId);
            $channel->update([
                'name' => $this->formName,
                'type' => $this->formType,
                'config' => $config ?: null,
            ]);
        } else {
            SjChannel::create([
                'team_id' => $team->id,
                'name' => $this->formName,
                'type' => $this->formType,
                'config' => $config ?: null,
            ]);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleStatus(int $id): void
    {
        $team = Auth::user()->currentTeam;
        $channel = SjChannel::where('team_id', $team->id)->findOrFail($id);
        $channel->update([
            'status' => $channel->status === 'active' ? 'paused' : 'active',
        ]);
    }

    public function deleteChannel(int $id): void
    {
        $team = Auth::user()->currentTeam;
        $channel = SjChannel::where('team_id', $team->id)->findOrFail($id);
        $channel->delete();
    }

    private function resetForm(): void
    {
        $this->editingChannelId = null;
        $this->formName = '';
        $this->formType = 'instagram';
        $this->formIntegrationConnectionId = null;
        $this->formInstagramAccountId = null;
        $this->formDefaultHashtags = '';
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $channels = SjChannel::where('team_id', $team->id)
            ->withCount('posts')
            ->with(['posts' => fn($q) => $q->latest('published_at')->limit(1)])
            ->orderBy('name')
            ->get();

        // Available Instagram accounts for the modal
        $instagramAccounts = IntegrationsInstagramAccount::whereHas('integrationConnection', function ($q) use ($team) {
            $q->whereHas('shares', fn($s) => $s->where('team_id', $team->id)->orWhereNull('team_id'));
        })->get();

        // Available integration connections
        $integrationConnections = IntegrationConnection::whereHas('shares', fn($q) => $q->where('team_id', $team->id)->orWhereNull('team_id'))
            ->whereHas('integration', fn($q) => $q->where('key', 'meta'))
            ->get();

        return view('syltjunkie::livewire.channel-index', [
            'channels' => $channels,
            'instagramAccounts' => $instagramAccounts,
            'integrationConnections' => $integrationConnections,
        ])->layout('platform::layouts.app');
    }
}
