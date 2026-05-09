<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Content', 'href' => route('syltjunkie.content.index')],
            ['label' => $contentPieceId ? 'Bearbeiten' : 'Neu'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Header --}}
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">
                    {{ $contentPieceId ? 'Content Piece bearbeiten' : 'Neues Content Piece' }}
                </h1>
                <div class="flex items-center gap-2">
                    <a href="{{ route('syltjunkie.content.index') }}" wire:navigate
                       class="px-3 py-1.5 text-[13px] text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                        Abbrechen
                    </a>
                    <button wire:click="save" class="px-4 py-1.5 bg-blue-600 text-white text-[13px] font-medium rounded-md hover:bg-blue-700">
                        Speichern
                    </button>
                </div>
            </div>

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 text-[13px] px-4 py-2 rounded-md">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Main Column --}}
                <div class="lg:col-span-2 space-y-4">
                    {{-- Title & Slug --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                        <div>
                            <label class="block text-[11px] font-medium text-gray-500 uppercase tracking-wide mb-1">Titel</label>
                            <input type="text" wire:model.blur="title"
                                   class="w-full rounded-md border-gray-300 text-[14px] px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="Content-Titel..." />
                            @error('title') <span class="text-[11px] text-red-500">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-gray-500 uppercase tracking-wide mb-1">Slug</label>
                            <input type="text" wire:model="slug"
                                   class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 font-mono focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="content-slug" />
                            @error('slug') <span class="text-[11px] text-red-500">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Brief Notes --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <label class="block text-[11px] font-medium text-gray-500 uppercase tracking-wide mb-1">Brief / Notizen</label>
                        <textarea wire:model="briefNotes" rows="4"
                                  class="w-full rounded-md border-gray-300 text-[13px] px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                  placeholder="Zielgruppe, Tonality, Key Messages, SEO-Fokus..."></textarea>
                    </div>

                    {{-- Body Markdown --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <label class="block text-[11px] font-medium text-gray-500 uppercase tracking-wide mb-1">Content (Markdown)</label>
                        <textarea wire:model="bodyMarkdown" rows="20"
                                  class="w-full rounded-md border-gray-300 text-[13px] px-3 py-2 font-mono leading-relaxed focus:border-blue-500 focus:ring-blue-500"
                                  placeholder="# Headline&#10;&#10;Content hier in Markdown schreiben..."></textarea>
                    </div>

                    {{-- Excerpt --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <label class="block text-[11px] font-medium text-gray-500 uppercase tracking-wide mb-1">Excerpt / Teaser</label>
                        <textarea wire:model="excerpt" rows="3"
                                  class="w-full rounded-md border-gray-300 text-[13px] px-3 py-2 focus:border-blue-500 focus:ring-blue-500"
                                  placeholder="Kurzbeschreibung f&uuml;r Social Media und Meta-Description..."></textarea>
                        <div class="text-[10px] text-gray-400 mt-1">Wird als Caption-Vorlage f&uuml;r Social Posts und als Meta-Description verwendet.</div>
                    </div>

                    {{-- SEO --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                        <div class="text-[11px] font-medium text-gray-500 uppercase tracking-wide">SEO</div>
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1">SEO Titel (optional, sonst Content-Titel)</label>
                            <input type="text" wire:model="seoTitle"
                                   class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="{{ $title ?: 'SEO-Titel' }}" />
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-400 mb-1">Meta Description</label>
                            <textarea wire:model="seoDescription" rows="2"
                                      class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                                      placeholder="Meta-Description f&uuml;r Suchmaschinen..."></textarea>
                        </div>
                    </div>
                </div>

                {{-- Sidebar Column --}}
                <div class="space-y-4">
                    {{-- Status & Type --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                        <div>
                            <label class="block text-[11px] font-medium text-gray-500 uppercase tracking-wide mb-1">Status</label>
                            <select wire:model="status" class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                                <option value="brief">Brief</option>
                                <option value="draft">Draft</option>
                                <option value="review">Review</option>
                                <option value="published">Published</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-gray-500 uppercase tracking-wide mb-1">Content-Typ</label>
                            <select wire:model="contentType" class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                                <option value="guide">Guide</option>
                                <option value="entity_page">Entity Page</option>
                                <option value="listing_page">Listing Page</option>
                                <option value="seasonal_guide">Seasonal Guide</option>
                                <option value="event">Event</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-gray-500 uppercase tracking-wide mb-1">Traffic-Ziel</label>
                            <input type="number" wire:model="targetTrafficEstimate"
                                   class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5"
                                   placeholder="Erwartete Besucher/Monat" />
                        </div>
                    </div>

                    {{-- Keywords --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <div class="text-[11px] font-medium text-gray-500 uppercase tracking-wide mb-2">Keywords</div>

                        {{-- Selected Keywords --}}
                        @if(count($selectedKeywordIds))
                        <div class="flex flex-wrap gap-1.5 mb-3">
                            @foreach($selectedKeywords as $kw)
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-[11px]
                                {{ $kw->id === $primaryKeywordId ? 'bg-blue-100 text-blue-700 font-medium' : 'bg-gray-100 text-gray-600' }}">
                                <button wire:click="setPrimaryKeyword({{ $kw->id }})" title="Als Primary setzen" class="hover:text-blue-800">
                                    @if($kw->id === $primaryKeywordId)
                                        @svg('heroicon-s-star', 'w-3 h-3')
                                    @else
                                        @svg('heroicon-o-star', 'w-3 h-3')
                                    @endif
                                </button>
                                {{ $kw->keyword }}
                                <span class="text-[10px] opacity-60">{{ number_format($kw->search_volume ?? 0) }}</span>
                                <button wire:click="toggleKeyword({{ $kw->id }})" class="ml-0.5 hover:text-red-500">&times;</button>
                            </span>
                            @endforeach
                        </div>
                        @endif

                        <input type="text" wire:model.live.debounce.300ms="keywordSearch"
                               placeholder="Keyword suchen..."
                               class="w-full rounded-md border-gray-300 text-[12px] px-2 py-1 mb-2" />

                        <div class="max-h-40 overflow-y-auto space-y-0.5">
                            @foreach($availableKeywords as $kw)
                                @if(!in_array($kw->id, $selectedKeywordIds))
                                <button wire:click="toggleKeyword({{ $kw->id }})"
                                        class="w-full text-left px-2 py-1 rounded text-[11px] text-gray-600 hover:bg-gray-50 flex justify-between">
                                    <span>{{ $kw->keyword }}</span>
                                    <span class="text-[10px] text-gray-400 tabular-nums">{{ number_format($kw->search_volume ?? 0) }}</span>
                                </button>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    {{-- Entities --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <div class="text-[11px] font-medium text-gray-500 uppercase tracking-wide mb-2">Entities</div>

                        {{-- Selected Entities --}}
                        @if(count($selectedEntityIds))
                        <div class="flex flex-wrap gap-1.5 mb-3">
                            @foreach($selectedEntities as $entity)
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-[11px] bg-gray-100 text-gray-600">
                                <span class="w-2 h-2 rounded-full" style="background-color: {{ $entity->entityType?->color ?? '#6B7280' }}"></span>
                                {{ $entity->name }}
                                <button wire:click="toggleEntity({{ $entity->id }})" class="ml-0.5 hover:text-red-500">&times;</button>
                            </span>
                            @endforeach
                        </div>
                        @endif

                        <input type="text" wire:model.live.debounce.300ms="entitySearch"
                               placeholder="Entity suchen..."
                               class="w-full rounded-md border-gray-300 text-[12px] px-2 py-1 mb-2" />

                        <div class="max-h-40 overflow-y-auto space-y-0.5">
                            @foreach($availableEntities as $entity)
                                @if(!in_array($entity->id, $selectedEntityIds))
                                <button wire:click="toggleEntity({{ $entity->id }})"
                                        class="w-full text-left px-2 py-1 rounded text-[11px] text-gray-600 hover:bg-gray-50 flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $entity->entityType?->color ?? '#6B7280' }}"></span>
                                    <span>{{ $entity->name }}</span>
                                    <span class="text-[10px] text-gray-400 ml-auto">{{ $entity->entityType?->name }}</span>
                                </button>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    {{-- Cover Image --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <div class="text-[11px] font-medium text-gray-500 uppercase tracking-wide mb-2">Cover-Bild</div>

                        @if($coverImageId)
                            @php $coverImg = $images->firstWhere('id', $coverImageId); @endphp
                            @if($coverImg)
                            <div class="relative mb-3">
                                <img src="{{ $coverImg->thumbnail_url }}" alt="{{ $coverImg->title }}"
                                     class="w-full h-32 object-cover rounded-md" />
                                <button wire:click="setCoverImage({{ $coverImageId }})"
                                        class="absolute top-1 right-1 bg-white/80 rounded-full p-1 hover:bg-white text-gray-500 hover:text-red-500">
                                    @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                </button>
                            </div>
                            @endif
                        @endif

                        <input type="text" wire:model.live.debounce.300ms="imageSearch"
                               placeholder="Bild suchen..."
                               class="w-full rounded-md border-gray-300 text-[12px] px-2 py-1 mb-2" />

                        <div class="grid grid-cols-3 gap-1.5 max-h-48 overflow-y-auto">
                            @foreach($images as $image)
                            <button wire:click="setCoverImage({{ $image->id }})"
                                    class="relative rounded overflow-hidden aspect-square {{ $coverImageId === $image->id ? 'ring-2 ring-blue-500' : 'hover:opacity-80' }}">
                                <img src="{{ $image->thumbnail_url }}" alt="{{ $image->title }}"
                                     class="w-full h-full object-cover" />
                                @if($coverImageId === $image->id)
                                <div class="absolute inset-0 bg-blue-500/20 flex items-center justify-center">
                                    @svg('heroicon-s-check-circle', 'w-5 h-5 text-blue-600')
                                </div>
                                @endif
                            </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
