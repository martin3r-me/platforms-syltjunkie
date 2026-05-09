<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationsInstagramAccount;
use Platform\Integrations\Models\IntegrationsFacebookPage;
use Platform\Syltjunkie\Models\SjChannel;
use Platform\Syltjunkie\Models\SjFacebookPost;
use Platform\Syltjunkie\Models\SjInstagramAccountInsight;
use Platform\Syltjunkie\Models\SjInstagramMedia;
use Platform\Syltjunkie\Services\SjFacebookPageService;
use Platform\Syltjunkie\Services\SjInstagramMediaService;
use Platform\Syltjunkie\Services\SjInstagramInsightsService;

class ChannelDetail extends Component
{
    use WithPagination;

    public SjChannel $channel;
    public string $mediaFilter = 'all';

    public function mount(SjChannel $channel): void
    {
        $team = Auth::user()->currentTeam;
        abort_if($channel->team_id !== $team->id, 403);

        $this->channel = $channel;
    }

    public function updatingMediaFilter(): void
    {
        $this->resetPage();
    }

    public function syncMedia(): void
    {
        if ($this->channel->type === 'instagram') {
            $this->syncInstagramMedia();
        } elseif ($this->channel->type === 'facebook') {
            $this->syncFacebookPosts();
        }
    }

    public function syncInsights(): void
    {
        if ($this->channel->type !== 'instagram') {
            return;
        }

        $connection = IntegrationConnection::find($this->channel->integration_connection_id);
        $account = IntegrationsInstagramAccount::find($this->channel->instagram_account_id);

        if (!$connection || !$account) {
            session()->flash('error', 'Integration Connection oder Instagram Account nicht gefunden.');
            return;
        }

        try {
            $insightsService = app(SjInstagramInsightsService::class);
            $insightsService->syncAccountInsights($account, $connection);
            $mediaResult = $insightsService->syncMediaInsights($account, $connection);
            session()->flash('success', "Account Insights + {$mediaResult['synced']} Media Insights synchronisiert.");
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler: ' . $e->getMessage());
        }
    }

    protected function syncInstagramMedia(): void
    {
        $connection = IntegrationConnection::find($this->channel->integration_connection_id);
        $account = IntegrationsInstagramAccount::find($this->channel->instagram_account_id);

        if (!$connection || !$account) {
            session()->flash('error', 'Integration Connection oder Instagram Account nicht gefunden.');
            return;
        }

        try {
            $mediaService = app(SjInstagramMediaService::class);
            $result = $mediaService->syncMedia($account, $connection, $this->channel->team_id);
            session()->flash('success', count($result) . ' Media-Items synchronisiert.');
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler: ' . $e->getMessage());
        }
    }

    protected function syncFacebookPosts(): void
    {
        $page = IntegrationsFacebookPage::find($this->channel->facebook_page_id);

        if (!$page) {
            session()->flash('error', 'Facebook Page nicht gefunden.');
            return;
        }

        try {
            $service = app(SjFacebookPageService::class);
            $result = $service->syncPosts($page, $this->channel->team_id);
            session()->flash('success', count($result) . ' Facebook-Posts synchronisiert.');
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $instagramAccount = null;
        $accountInsight = null;
        $media = null;
        $mediaStats = null;
        $facebookPage = null;
        $facebookPosts = null;

        if ($this->channel->type === 'instagram' && $this->channel->instagram_account_id) {
            $instagramAccount = IntegrationsInstagramAccount::find($this->channel->instagram_account_id);

            $accountInsight = SjInstagramAccountInsight::where('instagram_account_id', $this->channel->instagram_account_id)
                ->orderByDesc('insight_date')
                ->first();

            $mediaQuery = SjInstagramMedia::where('instagram_account_id', $this->channel->instagram_account_id)
                ->where('team_id', $team->id);

            if ($this->mediaFilter !== 'all') {
                if ($this->mediaFilter === 'story') {
                    $mediaQuery->where('is_story', true);
                } else {
                    $mediaQuery->where('media_type', strtoupper($this->mediaFilter))
                        ->where('is_story', false);
                }
            }

            $media = $mediaQuery
                ->with('latestInsight')
                ->orderByDesc('timestamp')
                ->paginate(24);

            $mediaStats = [
                'total' => SjInstagramMedia::where('instagram_account_id', $this->channel->instagram_account_id)->where('team_id', $team->id)->count(),
                'images' => SjInstagramMedia::where('instagram_account_id', $this->channel->instagram_account_id)->where('team_id', $team->id)->where('media_type', 'IMAGE')->count(),
                'videos' => SjInstagramMedia::where('instagram_account_id', $this->channel->instagram_account_id)->where('team_id', $team->id)->where('media_type', 'VIDEO')->count(),
                'carousels' => SjInstagramMedia::where('instagram_account_id', $this->channel->instagram_account_id)->where('team_id', $team->id)->where('media_type', 'CAROUSEL_ALBUM')->count(),
                'reels' => SjInstagramMedia::where('instagram_account_id', $this->channel->instagram_account_id)->where('team_id', $team->id)->where('media_type', 'REEL')->count(),
            ];
        }

        if ($this->channel->type === 'facebook' && $this->channel->facebook_page_id) {
            $facebookPage = IntegrationsFacebookPage::find($this->channel->facebook_page_id);

            $facebookPosts = SjFacebookPost::where('facebook_page_id', $this->channel->facebook_page_id)
                ->where('team_id', $team->id)
                ->orderByDesc('published_at')
                ->paginate(24);
        }

        $postCount = $this->channel->posts()->count();
        $lastPost = $this->channel->posts()->latest('published_at')->first();

        return view('syltjunkie::livewire.channel-detail', [
            'instagramAccount' => $instagramAccount,
            'accountInsight' => $accountInsight,
            'media' => $media,
            'mediaStats' => $mediaStats,
            'facebookPage' => $facebookPage,
            'facebookPosts' => $facebookPosts,
            'postCount' => $postCount,
            'lastPost' => $lastPost,
        ])->layout('platform::layouts.app');
    }
}
