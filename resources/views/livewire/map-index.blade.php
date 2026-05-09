<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Karte'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Karte &mdash; {{ count($mapPoints) }} Entities</h1>
                <select wire:model.live="filterGroupId" class="rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                    <option value="">Alle Gruppen</option>
                    @foreach($groups as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>

            @if(count($mapPoints))
            <div
                x-data="fullMap()"
                x-init="initMap()"
                class="relative"
            >
                <div wire:ignore id="full-map" class="w-full rounded-lg border border-gray-200 z-0"
                     style="height: calc(100vh - 220px); min-height: 400px;"
                     :class="{ '!fixed !inset-0 !w-full !h-full !rounded-none !border-0 !z-[9999]': fullscreen }"
                ></div>
                <button
                    @click="toggleFullscreen()"
                    class="absolute top-2 left-2 z-[1000] bg-white border border-gray-300 rounded px-2 py-1 text-[11px] text-gray-600 hover:bg-gray-50 shadow-sm"
                    x-text="fullscreen ? 'Vollbild beenden' : 'Vollbild'"
                ></button>
            </div>
            @else
            <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                    @svg('heroicon-o-map', 'w-6 h-6 text-gray-400')
                </div>
                <h3 class="text-[13px] font-semibold text-gray-700 mb-1">Keine Entities mit Koordinaten</h3>
                <p class="text-[12px] text-gray-400">F&uuml;ge Koordinaten zu Entities hinzu, um sie auf der Karte zu sehen.</p>
            </div>
            @endif
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>

    @if(count($mapPoints))
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('fullMap', () => ({
            map: null,
            fullscreen: false,

            toggleFullscreen() {
                this.fullscreen = !this.fullscreen;
                this.$nextTick(() => this.map.invalidateSize());
            },

            initMap() {
                const entities = @json($mapPoints);

                this.map = L.map('full-map').setView([54.9079, 8.3047], 11);

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
