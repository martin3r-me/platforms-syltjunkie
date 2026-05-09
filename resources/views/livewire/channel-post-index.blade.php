<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Posts'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-[13px] text-red-700">
            {{ session('error') }}
        </div>
        @endif
        @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-3 text-[13px] text-green-700">
            {{ session('success') }}
        </div>
        @endif

        <div class="space-y-6">
            {{-- Header --}}
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Posts</h1>
                    <p class="text-[13px] text-gray-500 mt-1">Alle Channel-Posts</p>
                </div>
                <a href="{{ route('syltjunkie.posts.create') }}" wire:navigate
                    class="rounded-lg bg-blue-600 px-4 py-2 text-[13px] font-medium text-white hover:bg-blue-700">
                    Post erstellen
                </a>
            </div>

            {{-- Filters --}}
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[160px]">
                    <label class="block text-[11px] text-gray-400 uppercase tracking-wider mb-1">Status</label>
                    <select wire:model.live="filterStatus"
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="">Alle</option>
                        <option value="draft">Entwurf</option>
                        <option value="scheduled">Geplant</option>
                        <option value="publishing">Wird veröffentlicht</option>
                        <option value="published">Veröffentlicht</option>
                        <option value="failed">Fehlgeschlagen</option>
                    </select>
                </div>
                <div class="min-w-[160px]">
                    <label class="block text-[11px] text-gray-400 uppercase tracking-wider mb-1">Channel</label>
                    <select wire:model.live="filterChannel"
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="">Alle Channels</option>
                        @foreach($channels as $ch)
                            <option value="{{ $ch->id }}">{{ $ch->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Post List --}}
            <div class="space-y-3">
                @forelse($posts as $post)
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-start gap-4">
                        {{-- Thumbnail --}}
                        <div class="flex-shrink-0 w-16 h-16 rounded-lg overflow-hidden bg-gray-100">
                            @if($post->images->first())
                                <img src="{{ $post->images->first()->thumbnail_url }}" alt="" class="w-full h-full object-cover" />
                            @else
                                <div class="w-full h-full flex items-center justify-center">
                                    @svg('heroicon-o-photo', 'w-6 h-6 text-gray-300')
                                </div>
                            @endif
                        </div>

                        {{-- Content --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium
                                    {{ match($post->status) {
                                        'draft' => 'bg-gray-100 text-gray-600',
                                        'scheduled' => 'bg-yellow-50 text-yellow-700',
                                        'publishing' => 'bg-blue-50 text-blue-700',
                                        'published' => 'bg-green-50 text-green-700',
                                        'failed' => 'bg-red-50 text-red-700',
                                        default => 'bg-gray-100 text-gray-600',
                                    } }}">
                                    {{ match($post->status) {
                                        'draft' => 'Entwurf',
                                        'scheduled' => 'Geplant',
                                        'publishing' => 'Wird veröffentlicht',
                                        'published' => 'Veröffentlicht',
                                        'failed' => 'Fehlgeschlagen',
                                        default => $post->status,
                                    } }}
                                </span>
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-500">
                                    {{ $post->post_type }}
                                </span>
                                <span class="text-[11px] text-gray-400">{{ $post->channel?->name }}</span>
                                @if($post->entity)
                                <span class="text-[11px] text-gray-400">&middot; {{ $post->entity->name }}</span>
                                @endif
                            </div>
                            <div class="text-[13px] text-gray-900 line-clamp-2">{{ $post->caption }}</div>
                            <div class="flex items-center gap-3 mt-1.5 text-[11px] text-gray-400">
                                <span>{{ $post->created_at->format('d.m.Y H:i') }}</span>
                                @if($post->scheduled_at)
                                <span>Geplant: {{ $post->scheduled_at->format('d.m.Y H:i') }}</span>
                                @endif
                                @if($post->published_at)
                                <span>Veröffentlicht: {{ $post->published_at->format('d.m.Y H:i') }}</span>
                                @endif
                                @if($post->images->count() > 1)
                                <span>{{ $post->images->count() }} Bilder</span>
                                @endif
                            </div>
                            @if($post->error_message)
                            <div class="mt-1 text-[11px] text-red-500 truncate">{{ $post->error_message }}</div>
                            @endif
                        </div>

                        {{-- Actions --}}
                        <div class="flex items-center gap-1 flex-shrink-0">
                            @if(in_array($post->status, ['draft', 'scheduled']))
                            <button wire:click="publishPost({{ $post->id }})" wire:confirm="Post jetzt veröffentlichen?" title="Veröffentlichen"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50">
                                @svg('heroicon-o-paper-airplane', 'w-4 h-4')
                            </button>
                            @endif
                            @if($post->status === 'failed')
                            <button wire:click="retryPost({{ $post->id }})" title="Erneut versuchen"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-yellow-600 hover:bg-yellow-50">
                                @svg('heroicon-o-arrow-path', 'w-4 h-4')
                            </button>
                            @endif
                            <button wire:click="deletePost({{ $post->id }})" wire:confirm="Post wirklich löschen?"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50">
                                @svg('heroicon-o-trash', 'w-4 h-4')
                            </button>
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-12 text-[13px] text-gray-400">
                    Noch keine Posts erstellt.
                </div>
                @endforelse
            </div>

            {{-- Pagination --}}
            <div>
                {{ $posts->links() }}
            </div>
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
