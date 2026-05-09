<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Bilddatenbank'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Header --}}
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Bilddatenbank</h1>
                    <p class="text-[13px] text-gray-500 mt-1">{{ number_format($totalCount) }} Bilder</p>
                </div>
            </div>

            {{-- Filters & Upload --}}
            <div class="flex flex-wrap items-end gap-3">
                {{-- Search --}}
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-[11px] text-gray-400 uppercase tracking-wider mb-1">Suche</label>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Titel, Fotograf..."
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" />
                </div>

                {{-- Tag Filter --}}
                <div class="min-w-[160px]">
                    <label class="block text-[11px] text-gray-400 uppercase tracking-wider mb-1">Tag</label>
                    <select wire:model.live="filterTag"
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="">Alle Tags</option>
                        @foreach($allTags as $tag)
                            <option value="{{ $tag }}">{{ $tag }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Upload --}}
                <div x-data="{ dragging: false }" class="min-w-[200px]">
                    <label class="block text-[11px] text-gray-400 uppercase tracking-wider mb-1">Upload</label>
                    <div
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="dragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'))"
                        :class="{ 'border-blue-400 bg-blue-50': dragging }"
                        class="relative rounded-lg border-2 border-dashed border-gray-300 px-4 py-2 text-center cursor-pointer hover:border-gray-400 transition-colors"
                    >
                        <input type="file" wire:model="pendingUploads" multiple accept="image/*" x-ref="fileInput"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                        <div class="flex items-center justify-center gap-2 text-[13px] text-gray-500">
                            @svg('heroicon-o-arrow-up-tray', 'w-4 h-4')
                            <span>Bilder hochladen</span>
                        </div>
                    </div>
                </div>

                @if(count($pendingUploads ?? []))
                <button wire:click="uploadImages" wire:loading.attr="disabled"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-[13px] font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="uploadImages">{{ count($pendingUploads) }} hochladen</span>
                    <span wire:loading wire:target="uploadImages">Wird hochgeladen...</span>
                </button>
                @endif
            </div>

            {{-- Image Grid --}}
            @if($images->count())
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach($images as $image)
                <div class="group relative bg-white rounded-lg border border-gray-200 overflow-hidden hover:shadow-md transition-shadow"
                     x-data="{ editing: false, editTitle: @js($image->title ?? ''), newTag: '' }">
                    {{-- Thumbnail --}}
                    <div class="aspect-[4/3] relative overflow-hidden bg-gray-100">
                        <img src="{{ $image->thumbnail_url }}" alt="{{ $image->title }}"
                             class="w-full h-full object-cover" loading="lazy" />

                        {{-- Overlay badges --}}
                        <div class="absolute top-2 right-2 flex items-center gap-1">
                            @if($image->latitude && $image->longitude)
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white/80 text-gray-600" title="GPS: {{ $image->latitude }}, {{ $image->longitude }}">
                                @svg('heroicon-o-map-pin', 'w-3.5 h-3.5')
                            </span>
                            @endif
                            @if($image->entities->count())
                            <span class="inline-flex items-center justify-center px-1.5 h-6 rounded-full bg-white/80 text-[10px] font-medium text-gray-600" title="{{ $image->entities->pluck('name')->join(', ') }}">
                                {{ $image->entities->count() }}
                            </span>
                            @endif
                        </div>

                        {{-- Delete button on hover --}}
                        <button wire:click="deleteImage({{ $image->id }})" wire:confirm="Bild wirklich löschen?"
                            class="absolute top-2 left-2 hidden group-hover:inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-500/80 text-white hover:bg-red-600">
                            @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                        </button>
                    </div>

                    {{-- Info --}}
                    <div class="p-2">
                        {{-- Title (inline edit) --}}
                        <div x-show="!editing" @dblclick="editing = true" class="text-[13px] font-medium text-gray-900 truncate cursor-pointer" title="Doppelklick zum Bearbeiten">
                            {{ $image->title ?: 'Ohne Titel' }}
                        </div>
                        <div x-show="editing" x-cloak>
                            <input type="text" x-model="editTitle" @keydown.enter="$wire.updateTitle({{ $image->id }}, editTitle); editing = false"
                                @keydown.escape="editing = false" x-ref="titleInput" @focus="$el.select()"
                                x-init="$watch('editing', v => v && $nextTick(() => $refs.titleInput.focus()))"
                                class="w-full rounded border border-gray-300 px-2 py-1 text-[12px] focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" />
                        </div>

                        {{-- Tags --}}
                        <div class="flex flex-wrap items-center gap-1 mt-1">
                            @foreach($image->tags ?? [] as $tag)
                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[10px] bg-gray-100 text-gray-600">
                                {{ $tag }}
                                <button wire:click="removeTag({{ $image->id }}, '{{ $tag }}')" class="text-gray-400 hover:text-red-500">&times;</button>
                            </span>
                            @endforeach
                            <div class="inline-flex items-center" x-data>
                                <input type="text" x-model="newTag" @keydown.enter="if(newTag.trim()) { $wire.addTag({{ $image->id }}, newTag.trim()); newTag = ''; }"
                                    placeholder="+"
                                    class="w-12 focus:w-24 transition-all rounded border-0 bg-transparent px-1 py-0.5 text-[10px] text-gray-400 focus:bg-gray-50 focus:ring-0 focus:border-gray-200 focus:border" />
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $images->links() }}
            </div>
            @else
            <div class="text-center py-12 text-gray-400">
                <div class="mb-2">@svg('heroicon-o-photo', 'w-12 h-12 mx-auto text-gray-300')</div>
                <p class="text-[13px]">Noch keine Bilder vorhanden.</p>
                <p class="text-[12px] mt-1">Lade Bilder hoch, um die Bilddatenbank zu starten.</p>
            </div>
            @endif
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
