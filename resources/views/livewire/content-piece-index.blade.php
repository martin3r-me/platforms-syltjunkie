<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Content'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Content</h1>
                <a href="{{ route('syltjunkie.content.create') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-[13px] font-medium rounded-md hover:bg-blue-700 transition-colors">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    Neues Content Piece
                </a>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-4 gap-4">
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Gesamt</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $stats['total'] }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Briefs</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $stats['briefs'] }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-yellow-500 uppercase tracking-wide mb-1">Drafts</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $stats['drafts'] }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-green-500 uppercase tracking-wide mb-1">Published</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $stats['published'] }}</div>
                </div>
            </div>

            {{-- Filters --}}
            <div class="flex items-center gap-3">
                <div class="flex-1">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Titel oder Brief durchsuchen..."
                        class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                    />
                </div>
                <select wire:model.live="filterStatus" class="rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                    <option value="">Alle Status</option>
                    <option value="brief">Brief</option>
                    <option value="draft">Draft</option>
                    <option value="review">Review</option>
                    <option value="published">Published</option>
                    <option value="archived">Archived</option>
                </select>
                <select wire:model.live="filterType" class="rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                    <option value="">Alle Typen</option>
                    <option value="guide">Guide</option>
                    <option value="entity_page">Entity Page</option>
                    <option value="listing_page">Listing Page</option>
                    <option value="seasonal_guide">Seasonal Guide</option>
                    <option value="event">Event</option>
                </select>
            </div>

            {{-- Content Pieces Table --}}
            @if($contentPieces->count())
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th wire:click="sortBy('title')" class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700">
                                Titel
                                @if($sortField === 'title') <span>{!! $sortDir === 'asc' ? '&uarr;' : '&darr;' !!}</span> @endif
                            </th>
                            <th wire:click="sortBy('content_type')" class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700">
                                Typ
                                @if($sortField === 'content_type') <span>{!! $sortDir === 'asc' ? '&uarr;' : '&darr;' !!}</span> @endif
                            </th>
                            <th wire:click="sortBy('status')" class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700">
                                Status
                                @if($sortField === 'status') <span>{!! $sortDir === 'asc' ? '&uarr;' : '&darr;' !!}</span> @endif
                            </th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Primary KW</th>
                            <th class="px-4 py-2 text-center text-[11px] font-medium text-gray-500 uppercase">KWs</th>
                            <th class="px-4 py-2 text-center text-[11px] font-medium text-gray-500 uppercase">Entities</th>
                            <th class="px-4 py-2 text-center text-[11px] font-medium text-gray-500 uppercase">Posts</th>
                            <th wire:click="sortBy('updated_at')" class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700">
                                Aktualisiert
                                @if($sortField === 'updated_at') <span>{!! $sortDir === 'asc' ? '&uarr;' : '&darr;' !!}</span> @endif
                            </th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($contentPieces as $piece)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('syltjunkie.content.edit', $piece->id) }}" wire:navigate class="text-[13px] font-medium text-blue-600 hover:text-blue-800">
                                    {{ $piece->title }}
                                </a>
                                @if($piece->brief_notes)
                                    <div class="text-[11px] text-gray-400 mt-0.5 truncate max-w-xs">{{ Str::limit($piece->brief_notes, 60) }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600">
                                    {{ str_replace('_', ' ', $piece->content_type) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-medium
                                    {{ $piece->status === 'published' ? 'bg-green-50 text-green-600' : ($piece->status === 'draft' ? 'bg-yellow-50 text-yellow-600' : ($piece->status === 'review' ? 'bg-orange-50 text-orange-600' : ($piece->status === 'archived' ? 'bg-slate-50 text-slate-500' : 'bg-gray-50 text-gray-500'))) }}">
                                    {{ $piece->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-500">
                                {{ $piece->keywords->first()?->keyword ?? '&mdash;' }}
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-500 text-center tabular-nums">
                                {{ $piece->keywords_count }}
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-500 text-center tabular-nums">
                                {{ $piece->entities_count }}
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-500 text-center tabular-nums">
                                {{ $piece->channel_posts_count }}
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-500">
                                {{ $piece->updated_at->format('d.m.Y') }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    wire:click="deleteContentPiece({{ $piece->id }})"
                                    wire:confirm="Content Piece '{{ $piece->title }}' wirklich l&ouml;schen?"
                                    class="text-[11px] text-red-400 hover:text-red-600"
                                >
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $contentPieces->links() }}
            </div>
            @else
            <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                    @svg('heroicon-o-document-text', 'w-6 h-6 text-gray-400')
                </div>
                <h3 class="text-[13px] font-semibold text-gray-700 mb-1">Keine Content Pieces</h3>
                <p class="text-[12px] text-gray-400 mb-3">Erstelle ein Content Piece oder generiere einen Brief aus einem Keyword.</p>
                <a href="{{ route('syltjunkie.content.create') }}" wire:navigate
                   class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 text-white text-[12px] font-medium rounded-md hover:bg-blue-700">
                    @svg('heroicon-o-plus', 'w-3.5 h-3.5')
                    Neues Content Piece
                </a>
            </div>
            @endif
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
