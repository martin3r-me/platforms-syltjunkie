<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Syltjunkie</h1>
                <p class="text-[13px] text-gray-500 mt-1">Entity Graph &mdash; das digitale Tor zur Insel Sylt</p>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Entities</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $entityCount }}</div>
                    <div class="text-[11px] text-gray-400 mt-1">Erfasste Objekte</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Entity Types</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $typeCount }}</div>
                    <div class="text-[11px] text-gray-400 mt-1">Definierte Typen</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Gruppen</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $groupCount }}</div>
                    <div class="text-[11px] text-gray-400 mt-1">Type-Gruppen</div>
                </div>
            </div>

            {{-- Entity Map --}}
            @if($mapEntities->count())
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-[13px] font-semibold text-gray-700">Karte — {{ $mapEntities->count() }} Entities</h2>
                </div>
                <div
                    x-data="dashboardMap()"
                    x-init="initMap()"
                    class="relative"
                >
                    <div wire:ignore id="dashboard-map" class="w-full h-96 rounded-lg border border-gray-200 z-0"
                         :class="{ '!fixed !inset-0 !w-full !h-full !rounded-none !border-0 !z-[9999]': fullscreen }"
                    ></div>
                    <button
                        @click="toggleFullscreen()"
                        class="absolute top-2 left-2 z-[1000] bg-white border border-gray-300 rounded px-2 py-1 text-[11px] text-gray-600 hover:bg-gray-50 shadow-sm"
                        x-text="fullscreen ? 'Vollbild beenden' : 'Vollbild'"
                    ></button>
                </div>
            </div>
            @endif

            {{-- Recent Entities --}}
            @if($recentEntities->count())
            <div class="bg-white rounded-lg border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h2 class="text-[13px] font-semibold text-gray-700">Zuletzt erfasst</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($recentEntities as $entity)
                    <a href="{{ route('syltjunkie.entity.detail', $entity) }}" class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-md flex items-center justify-center"
                                 style="background-color: {{ $entity->entityType?->color ?? '#3B82F6' }}15; color: {{ $entity->entityType?->color ?? '#3B82F6' }};">
                                @svg('heroicon-o-building-storefront', 'w-4 h-4')
                            </div>
                            <div>
                                <div class="text-[13px] font-medium text-gray-900">{{ $entity->name }}</div>
                                <div class="text-[11px] text-gray-400">{{ $entity->entityType?->name }} &middot; {{ $entity->ort }}</div>
                            </div>
                        </div>
                        <div class="text-[11px] text-gray-400">
                            {{ $entity->created_at->diffForHumans() }}
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
            @else
            <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                    @svg('heroicon-o-globe-alt', 'w-6 h-6 text-gray-400')
                </div>
                <h3 class="text-[13px] font-semibold text-gray-700 mb-1">Noch keine Entities</h3>
                <p class="text-[12px] text-gray-400">Starte mit dem Anlegen von Entity Types und f&uuml;lle den Graph.</p>
            </div>
            @endif

            {{-- Trend Signals --}}
            @if($trendSignals->count())
            <div class="bg-white rounded-lg border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h2 class="text-[13px] font-semibold text-gray-700">Trend Signals</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($trendSignals as $signal)
                    <div class="flex items-start gap-3 px-4 py-3">
                        <div class="flex-shrink-0 mt-1">
                            <span class="inline-flex w-2 h-2 rounded-full {{ $signal->severity === 'action' ? 'bg-red-400' : ($signal->severity === 'watch' ? 'bg-yellow-400' : 'bg-blue-300') }}"></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-[13px] font-medium text-gray-900">{{ $signal->title }}</div>
                            <div class="flex items-center gap-2 mt-1 text-[11px] text-gray-400">
                                @if($signal->entity)
                                    <a href="{{ route('syltjunkie.entity.detail', $signal->entity) }}" class="text-blue-600 hover:text-blue-800">{{ $signal->entity->name }}</a>
                                    <span>&middot;</span>
                                @endif
                                <span class="px-1.5 py-0.5 rounded font-medium
                                    {{ $signal->severity === 'action' ? 'bg-red-50 text-red-600' : ($signal->severity === 'watch' ? 'bg-yellow-50 text-yellow-600' : 'bg-blue-50 text-blue-600') }}">
                                    {{ $signal->severity }}
                                </span>
                                <span>{{ $signal->detected_at->format('d.m.Y') }}</span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
    @if($mapEntities->count())
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('dashboardMap', () => ({
            map: null,
            fullscreen: false,

            toggleFullscreen() {
                this.fullscreen = !this.fullscreen;
                this.$nextTick(() => this.map.invalidateSize());
            },

            initMap() {
                const entities = @json($mapPoints);

                this.map = L.map('dashboard-map').setView([54.9079, 8.3047], 11);

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

                const satellitWithLabels = L.layerGroup([satellite, labels]);
                osm.addTo(this.map);

                L.control.layers({
                    'Karte': osm,
                    'Satellit': satellitWithLabels,
                }, null, { position: 'topright' }).addTo(this.map);

                const bounds = L.latLngBounds();

                function createColoredIcon(color) {
                    return L.divIcon({
                        html: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 36" width="28" height="42">
                            <path d="M12 0C5.4 0 0 5.4 0 12c0 9 12 24 12 24s12-15 12-24C24 5.4 18.6 0 12 0z" fill="${color}" stroke="#fff" stroke-width="1.5"/>
                            <circle cx="12" cy="12" r="5" fill="#fff" opacity="0.9"/>
                        </svg>`,
                        iconSize: [28, 42],
                        iconAnchor: [14, 42],
                        popupAnchor: [0, -42],
                        className: '',
                    });
                }

                entities.forEach((e) => {
                    const marker = L.marker([e.lat, e.lng], { icon: createColoredIcon(e.color) }).addTo(this.map);
                    marker.bindPopup(
                        `<div class="text-sm">` +
                        `<div class="font-semibold">${e.name}</div>` +
                        `<div class="text-gray-500 text-xs">${e.type || ''} ${e.ort ? '&middot; ' + e.ort : ''}</div>` +
                        `<a href="/syltjunkie/entities/${e.id}" class="text-blue-600 text-xs hover:underline mt-1 block">Details &rarr;</a>` +
                        `</div>`
                    );
                    bounds.extend([e.lat, e.lng]);
                });

                if (entities.length > 0) {
                    this.map.fitBounds(bounds.pad(0.15));
                }
            },
        }));
    });
    </script>
    @endif
</x-ui-page>
