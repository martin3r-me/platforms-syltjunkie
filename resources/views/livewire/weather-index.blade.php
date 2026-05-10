<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Wetter'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Wetter Sylt</h1>
                @if($lastFetch)
                    <span class="text-[11px] text-gray-400">Letztes Update: {{ $lastFetch->format('d.m.Y H:i') }}</span>
                @endif
            </div>

            {{-- Overview: All Orte with current weather --}}
            @if($entities->count())
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                @foreach($entities as $entity)
                    @php $weather = $currentWeather->get($entity->id); @endphp
                    <button
                        wire:click="$set('selectedSlug', '{{ $entity->slug }}')"
                        class="bg-white rounded-lg border p-3 text-left transition-colors hover:border-blue-300
                            {{ $selectedSlug === $entity->slug ? 'border-blue-400 ring-1 ring-blue-100' : 'border-gray-200' }}"
                    >
                        <div class="text-[13px] font-medium text-gray-900">{{ $entity->name }}</div>
                        @if($weather)
                            <div class="flex items-center gap-2 mt-1.5">
                                @if($weather->icon)
                                    <span class="text-lg">@include('syltjunkie::livewire.partials.weather-icon', ['icon' => $weather->icon])</span>
                                @endif
                                <span class="text-lg font-semibold text-gray-900 tabular-nums">
                                    {{ $weather->temperature_avg !== null ? number_format($weather->temperature_avg, 0) . '°' : '--' }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 mt-1 text-[11px] text-gray-400">
                                @if($weather->temperature_min !== null && $weather->temperature_max !== null)
                                    <span>{{ number_format($weather->temperature_min, 0) }}° / {{ number_format($weather->temperature_max, 0) }}°</span>
                                @endif
                                @if($weather->wind_speed_avg !== null)
                                    <span>{{ number_format($weather->wind_speed_avg, 0) }} km/h</span>
                                @endif
                            </div>
                        @else
                            <div class="text-[11px] text-gray-300 mt-2">Keine Daten</div>
                        @endif
                    </button>
                @endforeach
            </div>
            @else
            <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                    @svg('heroicon-o-sun', 'w-6 h-6 text-gray-400')
                </div>
                <h3 class="text-[13px] font-semibold text-gray-700 mb-1">Keine Ort-Entities</h3>
                <p class="text-[12px] text-gray-400">Es wurden keine aktiven Ort-Entities mit Koordinaten gefunden.</p>
            </div>
            @endif

            {{-- Detail: Selected entity --}}
            @if($selectedEntity && $currentDetail)
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <h2 class="text-[15px] font-semibold text-gray-900 mb-4">{{ $selectedEntity->name }} &mdash; Heute</h2>

                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                    <div>
                        <div class="text-[11px] text-gray-400 uppercase tracking-wider">Temperatur</div>
                        <div class="text-lg font-semibold text-gray-900 mt-0.5">
                            {{ $currentDetail->temperature_avg !== null ? number_format($currentDetail->temperature_avg, 1) . '°C' : '--' }}
                        </div>
                        @if($currentDetail->temperature_min !== null && $currentDetail->temperature_max !== null)
                            <div class="text-[11px] text-gray-400">{{ number_format($currentDetail->temperature_min, 1) }}° &ndash; {{ number_format($currentDetail->temperature_max, 1) }}°</div>
                        @endif
                    </div>
                    <div>
                        <div class="text-[11px] text-gray-400 uppercase tracking-wider">Niederschlag</div>
                        <div class="text-lg font-semibold text-gray-900 mt-0.5">
                            {{ $currentDetail->precipitation_mm !== null ? number_format($currentDetail->precipitation_mm, 1) . ' mm' : '--' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-[11px] text-gray-400 uppercase tracking-wider">Wind</div>
                        <div class="text-lg font-semibold text-gray-900 mt-0.5">
                            {{ $currentDetail->wind_speed_avg !== null ? number_format($currentDetail->wind_speed_avg, 0) . ' km/h' : '--' }}
                        </div>
                        @if($currentDetail->wind_gust_max !== null)
                            <div class="text-[11px] text-gray-400">Böen {{ number_format($currentDetail->wind_gust_max, 0) }} km/h</div>
                        @endif
                    </div>
                    <div>
                        <div class="text-[11px] text-gray-400 uppercase tracking-wider">Sonnenstunden</div>
                        <div class="text-lg font-semibold text-gray-900 mt-0.5">
                            {{ $currentDetail->sunshine_hours !== null ? number_format($currentDetail->sunshine_hours, 1) . ' h' : '--' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-[11px] text-gray-400 uppercase tracking-wider">Bewölkung</div>
                        <div class="text-lg font-semibold text-gray-900 mt-0.5">
                            {{ $currentDetail->cloud_cover_avg !== null ? $currentDetail->cloud_cover_avg . '%' : '--' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-[11px] text-gray-400 uppercase tracking-wider">Luftfeuchte</div>
                        <div class="text-lg font-semibold text-gray-900 mt-0.5">
                            {{ $currentDetail->relative_humidity_avg !== null ? $currentDetail->relative_humidity_avg . '%' : '--' }}
                        </div>
                    </div>
                </div>

                @if($currentDetail->dwd_station_id)
                    <div class="text-[11px] text-gray-300 mt-3">DWD Station: {{ $currentDetail->dwd_station_id }}</div>
                @endif
            </div>
            @endif

            {{-- 7-Day Forecast --}}
            @if($selectedEntity && $forecast->count())
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <h2 class="text-[13px] font-semibold text-gray-700 mb-3">7-Tage-Vorhersage &mdash; {{ $selectedEntity->name }}</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-[12px]">
                        <thead>
                            <tr class="text-left text-[10px] text-gray-400 uppercase tracking-wider border-b border-gray-100">
                                <th class="pb-2 pr-3">Tag</th>
                                <th class="pb-2 pr-3"></th>
                                <th class="pb-2 pr-3 text-right">Min</th>
                                <th class="pb-2 pr-3 text-right">Max</th>
                                <th class="pb-2 pr-3 text-right">Regen</th>
                                <th class="pb-2 pr-3 text-right">Wind</th>
                                <th class="pb-2 pr-3 text-right">Böen</th>
                                <th class="pb-2 pr-3 text-right">Sonne</th>
                                <th class="pb-2 text-right">Wolken</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($forecast as $day)
                            <tr class="border-b border-gray-50 hover:bg-gray-50/50">
                                <td class="py-2 pr-3 text-gray-900 font-medium whitespace-nowrap">
                                    {{ $day->date->locale('de')->isoFormat('dd, D. MMM') }}
                                </td>
                                <td class="py-2 pr-3">
                                    @if($day->icon)
                                        @include('syltjunkie::livewire.partials.weather-icon', ['icon' => $day->icon])
                                    @endif
                                </td>
                                <td class="py-2 pr-3 text-right text-gray-500 tabular-nums">
                                    {{ $day->temperature_min !== null ? number_format($day->temperature_min, 0) . '°' : '--' }}
                                </td>
                                <td class="py-2 pr-3 text-right text-gray-900 font-medium tabular-nums">
                                    {{ $day->temperature_max !== null ? number_format($day->temperature_max, 0) . '°' : '--' }}
                                </td>
                                <td class="py-2 pr-3 text-right tabular-nums {{ ($day->precipitation_mm ?? 0) > 0 ? 'text-blue-600' : 'text-gray-400' }}">
                                    {{ $day->precipitation_mm !== null ? number_format($day->precipitation_mm, 1) . ' mm' : '--' }}
                                </td>
                                <td class="py-2 pr-3 text-right text-gray-500 tabular-nums">
                                    {{ $day->wind_speed_avg !== null ? number_format($day->wind_speed_avg, 0) : '--' }}
                                </td>
                                <td class="py-2 pr-3 text-right tabular-nums {{ ($day->wind_gust_max ?? 0) >= 60 ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                                    {{ $day->wind_gust_max !== null ? number_format($day->wind_gust_max, 0) : '--' }}
                                </td>
                                <td class="py-2 pr-3 text-right text-yellow-600 tabular-nums">
                                    {{ $day->sunshine_hours !== null ? number_format($day->sunshine_hours, 1) . 'h' : '--' }}
                                </td>
                                <td class="py-2 text-right text-gray-500 tabular-nums">
                                    {{ $day->cloud_cover_avg !== null ? $day->cloud_cover_avg . '%' : '--' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @elseif($selectedEntity)
            <div class="bg-white rounded-lg border border-gray-200 p-6 text-center">
                <p class="text-[12px] text-gray-400">
                    Noch keine Wetterdaten vorhanden. Führe <code class="bg-gray-100 px-1.5 py-0.5 rounded text-[11px]">php artisan syltjunkie:fetch-weather</code> aus.
                </p>
            </div>
            @endif
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
