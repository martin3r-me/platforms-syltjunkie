<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Channels', 'href' => route('syltjunkie.channels.index')],
            ['label' => $channel->name],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Sync Status --}}
            @if($channel->sync_status === 'syncing')
                <div wire:poll.5s="refreshSyncStatus" class="rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 text-[13px] text-blue-800 flex items-center gap-2">
                    @svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')
                    Synchronisierung läuft...
                </div>
            @elseif($channel->sync_status === 'failed')
                <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-[13px] text-red-800">
                    Sync fehlgeschlagen: {{ $channel->sync_error }}
                </div>
            @elseif($channel->sync_status === 'completed' && $channel->last_synced_at)
                <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-[13px] text-green-800">
                    Zuletzt synchronisiert: {{ $channel->last_synced_at->format('d.m.Y H:i') }}
                </div>
            @endif

            {{-- Channel Header --}}
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg
                        {{ $channel->type === 'instagram' ? 'bg-pink-100 text-pink-600' : ($channel->type === 'facebook' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600') }}">
                        @if($channel->type === 'instagram')
                            @svg('heroicon-o-camera', 'w-5 h-5')
                        @elseif($channel->type === 'facebook')
                            @svg('heroicon-o-chat-bubble-left-right', 'w-5 h-5')
                        @else
                            @svg('heroicon-o-globe-alt', 'w-5 h-5')
                        @endif
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold text-gray-900">{{ $channel->name }}</h1>
                        <p class="text-[13px] text-gray-500">
                            {{ ucfirst($channel->type) }}
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium ml-1
                                {{ $channel->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                {{ $channel->status }}
                            </span>
                            &middot; {{ $postCount }} Posts
                            @if($lastPost?->published_at)
                                &middot; Letzter Post: {{ $lastPost->published_at->format('d.m.Y') }}
                            @endif
                        </p>
                    </div>
                </div>

                @if($channel->type === 'instagram' || $channel->type === 'facebook')
                <div class="flex items-center gap-2">
                    <button wire:click="dispatchSync"
                        @if($channel->isSyncing()) disabled @endif
                        class="rounded-lg border border-gray-200 px-3 py-1.5 text-[12px] font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                        @if($channel->isSyncing())
                            @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5 inline -mt-0.5 animate-spin') Syncing...
                        @else
                            @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5 inline -mt-0.5') Sync
                        @endif
                    </button>
                </div>
                @endif
            </div>

            @if($channel->type === 'instagram' && $instagramAccount)
                {{-- Account Info --}}
                @if($accountInsight)
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                    @if($accountInsight->current_followers)
                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="text-[11px] text-gray-400 uppercase tracking-wider">Follower</div>
                        <div class="text-lg font-semibold text-gray-900 mt-0.5">{{ number_format($accountInsight->current_followers) }}</div>
                    </div>
                    @endif
                    @if($accountInsight->current_follows)
                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="text-[11px] text-gray-400 uppercase tracking-wider">Following</div>
                        <div class="text-lg font-semibold text-gray-900 mt-0.5">{{ number_format($accountInsight->current_follows) }}</div>
                    </div>
                    @endif
                    @if($accountInsight->reach)
                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="text-[11px] text-gray-400 uppercase tracking-wider">Reach</div>
                        <div class="text-lg font-semibold text-gray-900 mt-0.5">{{ number_format($accountInsight->reach) }}</div>
                    </div>
                    @endif
                    @if($accountInsight->impressions)
                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="text-[11px] text-gray-400 uppercase tracking-wider">Impressions</div>
                        <div class="text-lg font-semibold text-gray-900 mt-0.5">{{ number_format($accountInsight->impressions) }}</div>
                    </div>
                    @endif
                    @if($accountInsight->total_interactions)
                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="text-[11px] text-gray-400 uppercase tracking-wider">Interactions</div>
                        <div class="text-lg font-semibold text-gray-900 mt-0.5">{{ number_format($accountInsight->total_interactions) }}</div>
                    </div>
                    @endif
                    @if($accountInsight->profile_views)
                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="text-[11px] text-gray-400 uppercase tracking-wider">Profilaufrufe</div>
                        <div class="text-lg font-semibold text-gray-900 mt-0.5">{{ number_format($accountInsight->profile_views) }}</div>
                    </div>
                    @endif
                </div>
                @endif

                {{-- Media Filter --}}
                @if($mediaStats)
                <div class="flex items-center gap-1 flex-wrap">
                    @php
                        $filters = [
                            'all' => "Alle ({$mediaStats['total']})",
                            'image' => "Bilder ({$mediaStats['images']})",
                            'video' => "Videos ({$mediaStats['videos']})",
                            'carousel_album' => "Carousel ({$mediaStats['carousels']})",
                            'reel' => "Reels ({$mediaStats['reels']})",
                        ];
                    @endphp
                    @foreach($filters as $key => $label)
                        <button wire:click="$set('mediaFilter', '{{ $key }}')"
                            class="rounded-md px-3 py-1.5 text-[12px] font-medium transition-colors
                                {{ $mediaFilter === $key ? 'bg-gray-900 text-white' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
                @endif

                {{-- Media Grid --}}
                @if($media && $media->count())
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    @foreach($media as $item)
                    <div class="group relative bg-white rounded-lg border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                        <div class="aspect-square relative overflow-hidden bg-gray-100">
                            @if($item->thumbnail)
                                <img src="{{ $item->thumbnail }}" alt="{{ $item->caption ? \Illuminate\Support\Str::limit($item->caption, 50) : '' }}"
                                     class="w-full h-full object-cover" loading="lazy" />
                            @elseif($item->thumbnail_url)
                                <img src="{{ $item->thumbnail_url }}" alt=""
                                     class="w-full h-full object-cover" loading="lazy" />
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-300">
                                    @svg('heroicon-o-photo', 'w-8 h-8')
                                </div>
                            @endif

                            {{-- Type badge --}}
                            <div class="absolute top-2 left-2">
                                @if($item->media_type === 'VIDEO' || $item->media_type === 'REEL')
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-black/50 text-white">
                                        @svg('heroicon-o-play', 'w-3.5 h-3.5')
                                    </span>
                                @elseif($item->media_type === 'CAROUSEL_ALBUM')
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-black/50 text-white">
                                        @svg('heroicon-o-squares-2x2', 'w-3.5 h-3.5')
                                    </span>
                                @endif
                            </div>

                            {{-- Permalink --}}
                            @if($item->permalink)
                            <a href="{{ $item->permalink }}" target="_blank" rel="noopener"
                                class="absolute top-2 right-2 hidden group-hover:inline-flex items-center justify-center w-6 h-6 rounded-full bg-white/80 text-gray-600 hover:bg-white">
                                @svg('heroicon-o-arrow-top-right-on-square', 'w-3.5 h-3.5')
                            </a>
                            @endif
                        </div>

                        <div class="p-2">
                            <div class="flex items-center gap-2 text-[12px] text-gray-500">
                                <div class="flex items-center gap-0.5">
                                    @svg('heroicon-o-heart', 'w-3.5 h-3.5 text-pink-500')
                                    {{ number_format($item->like_count) }}
                                </div>
                                <div class="flex items-center gap-0.5">
                                    @svg('heroicon-o-chat-bubble-left', 'w-3.5 h-3.5')
                                    {{ number_format($item->comments_count) }}
                                </div>
                                @if($item->latestInsight)
                                    @if($item->latestInsight->reach)
                                    <div class="flex items-center gap-0.5">
                                        @svg('heroicon-o-eye', 'w-3.5 h-3.5')
                                        {{ number_format($item->latestInsight->reach) }}
                                    </div>
                                    @endif
                                @endif
                                @if($item->timestamp)
                                    <span class="ml-auto text-[11px] text-gray-400">{{ $item->timestamp->format('d.m.Y') }}</span>
                                @endif
                            </div>
                            @if($item->caption)
                                <p class="text-[11px] text-gray-500 mt-1 line-clamp-2">{{ $item->caption }}</p>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    {{ $media->links() }}
                </div>
                @elseif($media && $media->count() === 0)
                <div class="text-center py-12 text-gray-400">
                    <div class="mb-2">@svg('heroicon-o-photo', 'w-12 h-12 mx-auto text-gray-300')</div>
                    <p class="text-[13px]">Noch keine Media synchronisiert.</p>
                    <p class="text-[12px] mt-1">Klicke "Media sync" um Instagram-Inhalte zu laden.</p>
                </div>
                @endif
            @elseif($channel->type === 'instagram' && !$instagramAccount)
                <div class="text-center py-12 text-gray-400">
                    <div class="mb-2">@svg('heroicon-o-exclamation-triangle', 'w-12 h-12 mx-auto text-yellow-400')</div>
                    <p class="text-[13px]">Kein Instagram Account konfiguriert.</p>
                    <p class="text-[12px] mt-1">Bearbeite den Channel, um einen Instagram Account zuzuweisen.</p>
                </div>

            @elseif($channel->type === 'facebook' && $facebookPage)
                {{-- Facebook Posts Grid --}}
                @if($facebookPosts && $facebookPosts->count())
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    @foreach($facebookPosts as $fbPost)
                    <div class="group relative bg-white rounded-lg border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                        <div class="aspect-square relative overflow-hidden bg-gray-100">
                            @if($fbPost->thumbnail)
                                <img src="{{ $fbPost->thumbnail }}" alt=""
                                     class="w-full h-full object-cover" loading="lazy" />
                            @elseif($fbPost->media_url)
                                <img src="{{ $fbPost->media_url }}" alt=""
                                     class="w-full h-full object-cover" loading="lazy" />
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-300 p-4">
                                    @if($fbPost->message)
                                        <p class="text-[11px] text-gray-500 line-clamp-6 text-center">{{ $fbPost->message }}</p>
                                    @else
                                        @svg('heroicon-o-document-text', 'w-8 h-8')
                                    @endif
                                </div>
                            @endif

                            @if($fbPost->permalink_url)
                            <a href="{{ $fbPost->permalink_url }}" target="_blank" rel="noopener"
                                class="absolute top-2 right-2 hidden group-hover:inline-flex items-center justify-center w-6 h-6 rounded-full bg-white/80 text-gray-600 hover:bg-white">
                                @svg('heroicon-o-arrow-top-right-on-square', 'w-3.5 h-3.5')
                            </a>
                            @endif
                        </div>

                        <div class="p-2">
                            <div class="flex items-center gap-2 text-[12px] text-gray-500">
                                <div class="flex items-center gap-0.5">
                                    @svg('heroicon-o-hand-thumb-up', 'w-3.5 h-3.5 text-blue-500')
                                    {{ number_format($fbPost->like_count) }}
                                </div>
                                <div class="flex items-center gap-0.5">
                                    @svg('heroicon-o-chat-bubble-left', 'w-3.5 h-3.5')
                                    {{ number_format($fbPost->comment_count) }}
                                </div>
                                <div class="flex items-center gap-0.5">
                                    @svg('heroicon-o-share', 'w-3.5 h-3.5')
                                    {{ number_format($fbPost->share_count) }}
                                </div>
                                @if($fbPost->published_at)
                                    <span class="ml-auto text-[11px] text-gray-400">{{ $fbPost->published_at->format('d.m.Y') }}</span>
                                @endif
                            </div>
                            @if($fbPost->message)
                                <p class="text-[11px] text-gray-500 mt-1 line-clamp-2">{{ $fbPost->message }}</p>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    {{ $facebookPosts->links() }}
                </div>
                @else
                <div class="text-center py-12 text-gray-400">
                    <div class="mb-2">@svg('heroicon-o-document-text', 'w-12 h-12 mx-auto text-gray-300')</div>
                    <p class="text-[13px]">Noch keine Facebook-Posts synchronisiert.</p>
                    <p class="text-[12px] mt-1">Klicke "Posts sync" um Facebook-Inhalte zu laden.</p>
                </div>
                @endif

            @elseif($channel->type === 'facebook' && !$facebookPage)
                <div class="text-center py-12 text-gray-400">
                    <div class="mb-2">@svg('heroicon-o-exclamation-triangle', 'w-12 h-12 mx-auto text-yellow-400')</div>
                    <p class="text-[13px]">Keine Facebook Page konfiguriert.</p>
                    <p class="text-[12px] mt-1">Bearbeite den Channel, um eine Facebook Page zuzuweisen.</p>
                </div>

            @else
                {{-- Other channel types --}}
                <div class="text-center py-12 text-gray-400">
                    <div class="mb-2">@svg('heroicon-o-signal', 'w-12 h-12 mx-auto text-gray-300')</div>
                    <p class="text-[13px]">{{ ucfirst($channel->type) }} Channel</p>
                    <p class="text-[12px] mt-1">{{ $postCount }} Posts geplant oder veröffentlicht.</p>
                </div>
            @endif
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
