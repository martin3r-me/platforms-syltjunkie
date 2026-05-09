<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Keywords'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Keywords</h1>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Total Keywords</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($stats['total']) }}</div>
                    <div class="text-[11px] text-gray-400 mt-1">{{ number_format($stats['high_volume']) }} mit 1.000+ Suchen</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">&Oslash; Suchvolumen</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($stats['avg_volume']) }}</div>
                    <div class="text-[11px] text-gray-400 mt-1">Monatliche Suchanfragen</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Keyword Difficulty</div>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-50 text-green-700">{{ $stats['easy'] }} leicht</span>
                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-50 text-yellow-700">{{ $stats['medium'] }} mittel</span>
                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-50 text-red-700">{{ $stats['hard'] }} schwer</span>
                    </div>
                    <div class="text-[11px] text-gray-400 mt-1">Wie schwer ist es zu ranken</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Google Trends</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($stats['with_trends']) }}</div>
                    <div class="text-[11px] text-gray-400 mt-1">Keywords mit Trend-Daten</div>
                </div>
            </div>

            {{-- Filters --}}
            <div class="flex items-center gap-3 flex-wrap">
                <div class="flex-1 min-w-[200px]">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Keyword suchen..."
                        class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                    />
                </div>
                <select wire:model.live="filterIntent" class="rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                    <option value="">Alle Intents</option>
                    @foreach($intents as $intent)
                        <option value="{{ $intent }}">{{ ucfirst($intent) }}</option>
                    @endforeach
                </select>
                <select wire:model.live="filterTopic" class="rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                    <option value="">Alle Topics</option>
                    @foreach($topics as $topic)
                        <option value="{{ $topic }}">{{ ucfirst(str_replace('_', ' ', $topic)) }}</option>
                    @endforeach
                </select>
                <select wire:model.live="filterKd" class="rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                    <option value="">Alle KD</option>
                    <option value="easy">Leicht (0&ndash;30)</option>
                    <option value="medium">Mittel (31&ndash;60)</option>
                    <option value="hard">Schwer (61+)</option>
                </select>
                <select wire:model.live="filterTrend" class="rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                    <option value="">Alle Trends</option>
                    <option value="with">Mit Trend-Daten</option>
                    <option value="without">Ohne Trend-Daten</option>
                    <option value="high">Hohes Interesse (&ge;50)</option>
                </select>
                <input
                    type="number"
                    wire:model.live.debounce.500ms="volumeMin"
                    placeholder="Vol min"
                    class="w-24 rounded-md border-gray-300 text-[13px] px-3 py-1.5"
                />
                <input
                    type="number"
                    wire:model.live.debounce.500ms="volumeMax"
                    placeholder="Vol max"
                    class="w-24 rounded-md border-gray-300 text-[13px] px-3 py-1.5"
                />
            </div>

            {{-- Keywords Table --}}
            @if($keywords->count())
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th wire:click="sortBy('keyword')" class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700">
                                Keyword
                                @if($sortField === 'keyword') <span>{!! $sortDir === 'asc' ? '&uarr;' : '&darr;' !!}</span> @endif
                            </th>
                            <th wire:click="sortBy('search_volume')" class="px-4 py-2 text-right text-[11px] font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700" title="Monatliche Google-Suchanfragen">
                                Volume
                                @if($sortField === 'search_volume') <span>{!! $sortDir === 'asc' ? '&uarr;' : '&darr;' !!}</span> @endif
                            </th>
                            <th wire:click="sortBy('keyword_difficulty')" class="px-4 py-2 text-right text-[11px] font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700" title="Keyword Difficulty: 0&ndash;30 leicht (gr&uuml;n), 31&ndash;60 mittel (gelb), 61&ndash;100 schwer (rot)">
                                KD
                                @if($sortField === 'keyword_difficulty') <span>{!! $sortDir === 'asc' ? '&uarr;' : '&darr;' !!}</span> @endif
                            </th>
                            <th wire:click="sortBy('cpc_cents')" class="px-4 py-2 text-right text-[11px] font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700" title="Cost per Click: Was Werbetreibende pro Klick bei Google Ads zahlen">
                                CPC
                                @if($sortField === 'cpc_cents') <span>{!! $sortDir === 'asc' ? '&uarr;' : '&darr;' !!}</span> @endif
                            </th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase" title="Search Intent: Suchintention des Nutzers (informational, navigational, commercial, transactional)">Intent</th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase" title="Thematische Zuordnung des Keywords">Topic</th>
                            <th wire:click="sortBy('trends_average_interest')" class="px-4 py-2 text-center text-[11px] font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700" title="Google Trends Sparkline: Suchinteresse der letzten 12 Monate als Mini-Grafik">
                                Trend
                                @if($sortField === 'trends_average_interest') <span>{!! $sortDir === 'asc' ? '&uarr;' : '&darr;' !!}</span> @endif
                            </th>
                            <th wire:click="sortBy('trends_fetched_at')" class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700" title="Wann die Trend-Daten zuletzt von Google Trends geholt wurden">
                                Fetched
                                @if($sortField === 'trends_fetched_at') <span>{!! $sortDir === 'asc' ? '&uarr;' : '&darr;' !!}</span> @endif
                            </th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($keywords as $keyword)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-[13px] font-medium text-gray-900">
                                {{ $keyword->keyword }}
                            </td>
                            <td class="px-4 py-3 text-[13px] text-gray-700 tabular-nums text-right">
                                {{ $keyword->search_volume ? number_format($keyword->search_volume) : '&mdash;' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($keyword->keyword_difficulty !== null)
                                    <span class="inline-flex items-center justify-center w-8 h-6 rounded text-[11px] font-medium tabular-nums
                                        {{ $keyword->keyword_difficulty <= 30 ? 'bg-green-50 text-green-700' : ($keyword->keyword_difficulty <= 60 ? 'bg-yellow-50 text-yellow-700' : 'bg-red-50 text-red-700') }}"
                                        title="{{ $keyword->keyword_difficulty <= 30 ? 'Leicht zu ranken' : ($keyword->keyword_difficulty <= 60 ? 'Mittlerer Wettbewerb' : 'Stark umk&auml;mpft') }}">
                                        {{ $keyword->keyword_difficulty }}
                                    </span>
                                @else
                                    <span class="text-[11px] text-gray-400">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-500 tabular-nums text-right">
                                @if($keyword->cpc_euro !== null)
                                    &euro;{{ number_format($keyword->cpc_euro, 2) }}
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($keyword->search_intent)
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-medium
                                        {{ $keyword->search_intent === 'transactional' ? 'bg-green-50 text-green-600' : ($keyword->search_intent === 'commercial' ? 'bg-purple-50 text-purple-600' : ($keyword->search_intent === 'navigational' ? 'bg-blue-50 text-blue-600' : 'bg-gray-50 text-gray-500')) }}"
                                        title="{{ $keyword->search_intent === 'informational' ? 'Nutzer sucht Informationen' : ($keyword->search_intent === 'navigational' ? 'Nutzer sucht eine bestimmte Seite' : ($keyword->search_intent === 'commercial' ? 'Nutzer vergleicht Angebote' : 'Nutzer will kaufen/buchen')) }}">
                                        {{ $keyword->search_intent }}
                                    </span>
                                @else
                                    <span class="text-[11px] text-gray-400">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($keyword->topic)
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600">
                                        {{ str_replace('_', ' ', $keyword->topic) }}
                                    </span>
                                @else
                                    <span class="text-[11px] text-gray-400">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($keyword->trends_sparkline)
                                    <div class="inline-flex items-center gap-2">
                                        <div
                                            x-data="{ points: {{ json_encode($keyword->trends_sparkline) }} }"
                                            x-init="
                                                const vals = points;
                                                const max = Math.max(...vals);
                                                const min = Math.min(...vals);
                                                const range = max - min || 1;
                                                const w = 80;
                                                const h = 24;
                                                const coords = vals.map((v, i) => {
                                                    const x = (i / (vals.length - 1)) * w;
                                                    const y = h - ((v - min) / range) * (h - 2) - 1;
                                                    return x.toFixed(1) + ',' + y.toFixed(1);
                                                }).join(' ');
                                                $el.querySelector('polyline').setAttribute('points', coords);
                                            "
                                            class="inline-block"
                                            title="Google Trends: Suchinteresse der letzten 12 Monate (0&ndash;100)"
                                        >
                                            <svg width="80" height="24" class="overflow-visible">
                                                <polyline points="" fill="none" stroke="#3B82F6" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" />
                                            </svg>
                                        </div>
                                        @if($keyword->trends_average_interest)
                                        <span class="text-[10px] tabular-nums {{ $keyword->trends_average_interest >= 50 ? 'text-blue-600 font-medium' : 'text-gray-400' }}" title="Durchschnittliches Suchinteresse (0&ndash;100)">
                                            {{ $keyword->trends_average_interest }}
                                        </span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-[11px] text-gray-400">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-500">
                                {{ $keyword->trends_fetched_at?->format('d.m.Y') ?? '&mdash;' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('syltjunkie.content.create', ['keyword_id' => $keyword->id]) }}" wire:navigate
                                   class="inline-flex items-center gap-1 px-2 py-1 rounded text-[10px] font-medium text-blue-600 hover:bg-blue-50 transition-colors"
                                   title="Content Brief erstellen">
                                    @svg('heroicon-o-document-plus', 'w-3.5 h-3.5')
                                    Brief
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Legende --}}
            <div class="flex flex-wrap items-center gap-4 text-[11px] text-gray-400 px-1">
                <span><strong class="text-gray-500">Volume</strong> = monatl. Google-Suchen</span>
                <span><strong class="text-gray-500">KD</strong> = Keyword Difficulty (0&ndash;100, wie schwer zu ranken)</span>
                <span><strong class="text-gray-500">CPC</strong> = Cost per Click (Google Ads Klickpreis)</span>
                <span><strong class="text-gray-500">Trend</strong> = Google Trends Sparkline (12 Monate)</span>
            </div>

            <div class="mt-4">
                {{ $keywords->links() }}
            </div>
            @else
            <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                    @svg('heroicon-o-magnifying-glass', 'w-6 h-6 text-gray-400')
                </div>
                <h3 class="text-[13px] font-semibold text-gray-700 mb-1">Keine Keywords</h3>
                <p class="text-[12px] text-gray-400">Nutze den Discover-Keywords Command, um Keywords zu entdecken.</p>
            </div>
            @endif
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
