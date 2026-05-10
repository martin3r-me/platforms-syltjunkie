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
                    <p class="text-[13px] text-gray-500 mt-1">{{ number_format($totalCount) }} Bilder &middot; {{ number_format($geoCount) }} mit GPS &middot; {{ number_format($postedCount) }} gepostet &middot; {{ number_format($inContentCount) }} in Content</p>
                </div>
                <div class="flex items-center gap-1 rounded-lg border border-gray-200 p-0.5">
                    <button wire:click="$set('viewMode', 'grid')"
                        class="rounded-md px-3 py-1.5 text-[12px] font-medium transition-colors
                            {{ $viewMode === 'grid' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:text-gray-700' }}">
                        @svg('heroicon-o-squares-2x2', 'w-4 h-4 inline -mt-0.5') Grid
                    </button>
                    <button wire:click="$set('viewMode', 'map')"
                        class="rounded-md px-3 py-1.5 text-[12px] font-medium transition-colors
                            {{ $viewMode === 'map' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:text-gray-700' }}">
                        @svg('heroicon-o-map', 'w-4 h-4 inline -mt-0.5') Karte
                    </button>
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
                <div class="min-w-[140px]">
                    <label class="block text-[11px] text-gray-400 uppercase tracking-wider mb-1">Tag</label>
                    <select wire:model.live="filterTag"
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="">Alle Tags</option>
                        @foreach($allTags as $tag)
                            <option value="{{ $tag }}">{{ $tag }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Usage Filter --}}
                <div class="min-w-[140px]">
                    <label class="block text-[11px] text-gray-400 uppercase tracking-wider mb-1">Verwendung</label>
                    <select wire:model.live="filterUsage"
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <option value="">Alle</option>
                        <option value="used">Verwendet</option>
                        <option value="unused">Unverwendet</option>
                        <option value="posted">In Posts</option>
                        <option value="content">In Content</option>
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

            {{-- Upload Errors --}}
            @if(!empty($uploadErrors))
                <div class="rounded-md border border-red-200 bg-red-50 p-3">
                    <p class="text-[12px] font-medium text-red-800 mb-1">Abgelehnte Bilder (GPS + Aufnahmedatum erforderlich):</p>
                    <ul class="text-[12px] text-red-700 space-y-0.5">
                        @foreach($uploadErrors as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Map View --}}
            @if($viewMode === 'map')
            <div
                x-data="imageMap()"
                x-init="initMap()"
                class="relative"
            >
                <div wire:ignore id="image-map" class="w-full rounded-lg border border-gray-200 z-0"
                     style="height: 600px;"
                     :class="{ '!fixed !inset-0 !w-full !h-full !rounded-none !border-0 !z-[9999]': fullscreen }"
                ></div>
                <button
                    @click="toggleFullscreen()"
                    class="absolute top-2 left-2 z-[1000] bg-white border border-gray-300 rounded px-2 py-1 text-[11px] text-gray-600 hover:bg-gray-50 shadow-sm"
                    x-text="fullscreen ? 'Vollbild beenden' : 'Vollbild'"
                ></button>
                <div class="absolute top-2 right-14 z-[1000] bg-white border border-gray-300 rounded px-2 py-1 text-[11px] text-gray-500 shadow-sm">
                    {{ count($mapImages) }} Bilder auf der Karte
                </div>
            </div>
            @endif

            {{-- Image Grid --}}
            @if($viewMode === 'grid')
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
                                    @svg('heroicon-o-building-storefront', 'w-3 h-3 inline -mt-px') {{ $image->entities->count() }}
                                </span>
                                @endif
                            </div>

                            {{-- Usage badges (bottom) --}}
                            @if($image->channel_posts_count > 0 || $image->content_pieces_count > 0)
                            <div class="absolute bottom-2 left-2 flex items-center gap-1">
                                @if($image->channel_posts_count > 0)
                                <span class="inline-flex items-center gap-0.5 px-1.5 h-5 rounded-full bg-purple-500/80 text-[10px] font-medium text-white" title="{{ $image->channel_posts_count }} Post(s)">
                                    @svg('heroicon-o-paper-airplane', 'w-3 h-3') {{ $image->channel_posts_count }}
                                </span>
                                @endif
                                @if($image->content_pieces_count > 0)
                                <span class="inline-flex items-center gap-0.5 px-1.5 h-5 rounded-full bg-blue-500/80 text-[10px] font-medium text-white" title="{{ $image->content_pieces_count }} Content Piece(s)">
                                    @svg('heroicon-o-document-text', 'w-3 h-3') {{ $image->content_pieces_count }}
                                </span>
                                @endif
                            </div>
                            @endif

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
            @endif
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>

    @if($viewMode === 'map')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('imageMap', () => ({
            map: null,
            fullscreen: false,

            toggleFullscreen() {
                this.fullscreen = !this.fullscreen;
                this.$nextTick(() => this.map.invalidateSize());
            },

            initMap() {
                const images = @json($mapImages);
                const defaultCenter = [54.9079, 8.3047];
                const defaultZoom = 11;

                this.map = L.map('image-map').setView(defaultCenter, defaultZoom);

                const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap',
                    maxZoom: 19,
                });

                const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    attribution: '&copy; Esri',
                    maxZoom: 19,
                });

                const labels = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
                    maxZoom: 19,
                    pane: 'overlayPane',
                });

                const satelliteWithLabels = L.layerGroup([satellite, labels]);

                osm.addTo(this.map);

                L.control.layers({
                    'Karte': osm,
                    'Satellit': satelliteWithLabels,
                }, null, { position: 'topright' }).addTo(this.map);

                if (images.length === 0) return;

                const bounds = L.latLngBounds();

                images.forEach(img => {
                    const thumbIcon = L.divIcon({
                        html: `<div class="image-map-thumb" style="
                            width: 48px; height: 48px;
                            border-radius: 8px;
                            border: 3px solid #fff;
                            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
                            overflow: hidden;
                            background: #f3f4f6;
                        "><img src="${img.thumbnail}" style="width:100%;height:100%;object-fit:cover;" loading="lazy" /></div>`,
                        iconSize: [48, 48],
                        iconAnchor: [24, 24],
                        popupAnchor: [0, -28],
                        className: '',
                    });

                    const tags = img.tags.length ? `<div style="margin-top:4px;font-size:10px;color:#9ca3af;">${img.tags.join(', ')}</div>` : '';

                    const marker = L.marker([img.lat, img.lng], { icon: thumbIcon })
                        .bindPopup(`
                            <div style="text-align:center;min-width:160px;">
                                <img src="${img.thumbnail}" style="width:160px;height:120px;object-fit:cover;border-radius:6px;margin-bottom:6px;" />
                                <div style="font-size:13px;font-weight:500;color:#111;">${img.title}</div>
                                <div style="font-size:11px;color:#9ca3af;">${img.lat.toFixed(5)}, ${img.lng.toFixed(5)}</div>
                                ${tags}
                            </div>
                        `, { maxWidth: 200 })
                        .addTo(this.map);

                    bounds.extend([img.lat, img.lng]);
                });

                this.map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
            },
        }));
    });
    </script>
    @endif
</x-ui-page>
