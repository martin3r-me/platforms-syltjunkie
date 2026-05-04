<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Entities', 'href' => route('syltjunkie.entities.index')],
            ['label' => $entity->name],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Header --}}
            <div>
                <h1 class="text-xl font-semibold text-gray-900">{{ $entity->name }}</h1>
                <p class="text-[13px] text-gray-500 mt-1">
                    {{ $entity->entityType?->group?->name }} &rarr; {{ $entity->entityType?->name }}
                    @if($entity->ort) &middot; {{ $entity->ort }} @endif
                </p>
            </div>

            {{-- SEO Overview Cards (Website-URL) --}}
            @php
                $websiteUrl = $entity->entityUrls->firstWhere('platform', 'website');
                $urlSnapshot = $websiteUrl?->latestSnapshot;
                $pageSnapshot = $websiteUrl?->latestPageSnapshot;
            @endphp
            @if($urlSnapshot || $pageSnapshot)
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @if($urlSnapshot && $urlSnapshot->keywords_count)
                <div class="bg-white rounded-lg border border-gray-200 p-3">
                    <div class="text-[11px] text-gray-400 uppercase tracking-wider">Keywords</div>
                    <div class="text-lg font-semibold text-gray-900 mt-0.5">{{ number_format($urlSnapshot->keywords_count) }}</div>
                    <div class="text-[11px] text-gray-400 mt-0.5">rankend auf Google</div>
                </div>
                @endif
                @if($urlSnapshot && $urlSnapshot->organic_traffic_estimate)
                <div class="bg-white rounded-lg border border-gray-200 p-3">
                    <div class="text-[11px] text-gray-400 uppercase tracking-wider">Org. Traffic</div>
                    <div class="text-lg font-semibold text-gray-900 mt-0.5">~{{ number_format($urlSnapshot->organic_traffic_estimate) }}</div>
                    <div class="text-[11px] text-gray-400 mt-0.5">Besucher/Monat</div>
                </div>
                @endif
                @if($urlSnapshot && $urlSnapshot->organic_value_cents)
                <div class="bg-white rounded-lg border border-gray-200 p-3">
                    <div class="text-[11px] text-gray-400 uppercase tracking-wider">Traffic-Wert</div>
                    <div class="text-lg font-semibold text-gray-900 mt-0.5">&euro;{{ number_format($urlSnapshot->organic_value_cents / 100, 0, ',', '.') }}</div>
                    <div class="text-[11px] text-gray-400 mt-0.5">monatlicher CPC-Wert</div>
                </div>
                @endif
                @if($pageSnapshot && $pageSnapshot->onpage_score !== null)
                <div class="bg-white rounded-lg border border-gray-200 p-3">
                    <div class="text-[11px] text-gray-400 uppercase tracking-wider">OnPage Score</div>
                    <div class="text-lg font-semibold mt-0.5 {{ $pageSnapshot->onpage_score >= 80 ? 'text-green-600' : ($pageSnapshot->onpage_score >= 50 ? 'text-yellow-600' : 'text-red-600') }}">
                        {{ number_format($pageSnapshot->onpage_score, 1) }}
                    </div>
                    <div class="text-[11px] text-gray-400 mt-0.5">von 100</div>
                </div>
                @endif
            </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Main Content --}}
                <div class="lg:col-span-2 space-y-6">

                    {{-- Page Snapshot (On-Page SEO Data) --}}
                    @if($pageSnapshot)
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <h2 class="text-[13px] font-semibold text-gray-700 mb-3">On-Page SEO</h2>
                        <div class="space-y-3">
                            {{-- Title --}}
                            @if($pageSnapshot->title)
                            <div>
                                <div class="text-[11px] text-gray-400 uppercase tracking-wider">Title</div>
                                <div class="text-[13px] text-gray-900 mt-0.5 font-medium">{{ $pageSnapshot->title }}</div>
                                <div class="text-[11px] text-gray-400">{{ mb_strlen($pageSnapshot->title) }} Zeichen</div>
                            </div>
                            @endif

                            {{-- Meta Description --}}
                            @if($pageSnapshot->meta_description)
                            <div>
                                <div class="text-[11px] text-gray-400 uppercase tracking-wider">Meta Description</div>
                                <div class="text-[13px] text-gray-600 mt-0.5">{{ $pageSnapshot->meta_description }}</div>
                                <div class="text-[11px] text-gray-400">{{ mb_strlen($pageSnapshot->meta_description) }} Zeichen</div>
                            </div>
                            @endif

                            {{-- Headings --}}
                            @if($pageSnapshot->headings)
                            <div>
                                <div class="text-[11px] text-gray-400 uppercase tracking-wider mb-1">Headings</div>
                                @foreach(['h1', 'h2', 'h3'] as $level)
                                    @if(!empty($pageSnapshot->headings[$level]))
                                    <div class="ml-{{ $level === 'h1' ? '0' : ($level === 'h2' ? '2' : '4') }} mb-1">
                                        @foreach($pageSnapshot->headings[$level] as $heading)
                                        <div class="flex items-baseline gap-2 text-[12px]">
                                            <span class="text-gray-400 font-mono text-[10px] flex-shrink-0 w-5">{{ strtoupper($level) }}</span>
                                            <span class="{{ $level === 'h1' ? 'text-gray-900 font-medium' : 'text-gray-600' }}">{{ $heading }}</span>
                                        </div>
                                        @endforeach
                                    </div>
                                    @endif
                                @endforeach
                            </div>
                            @endif

                            {{-- Metrics Grid --}}
                            <div class="grid grid-cols-3 gap-3 pt-2 border-t border-gray-100">
                                @if($pageSnapshot->word_count)
                                <div>
                                    <div class="text-[11px] text-gray-400">Wortanzahl</div>
                                    <div class="text-[13px] text-gray-900 font-medium">{{ number_format($pageSnapshot->word_count) }}</div>
                                </div>
                                @endif
                                @if($pageSnapshot->internal_links_count !== null)
                                <div>
                                    <div class="text-[11px] text-gray-400">Interne Links</div>
                                    <div class="text-[13px] text-gray-900 font-medium">{{ $pageSnapshot->internal_links_count }}</div>
                                </div>
                                @endif
                                @if($pageSnapshot->external_links_count !== null)
                                <div>
                                    <div class="text-[11px] text-gray-400">Externe Links</div>
                                    <div class="text-[13px] text-gray-900 font-medium">{{ $pageSnapshot->external_links_count }}</div>
                                </div>
                                @endif
                                @if($pageSnapshot->image_count !== null)
                                <div>
                                    <div class="text-[11px] text-gray-400">Bilder</div>
                                    <div class="text-[13px] text-gray-900 font-medium">{{ $pageSnapshot->image_count }}</div>
                                </div>
                                @endif
                                @if($pageSnapshot->load_time !== null)
                                <div>
                                    <div class="text-[11px] text-gray-400">Ladezeit</div>
                                    <div class="text-[13px] font-medium {{ $pageSnapshot->load_time <= 2 ? 'text-green-600' : ($pageSnapshot->load_time <= 4 ? 'text-yellow-600' : 'text-red-600') }}">
                                        {{ number_format($pageSnapshot->load_time, 2) }}s
                                    </div>
                                </div>
                                @endif
                                @if($pageSnapshot->status_code)
                                <div>
                                    <div class="text-[11px] text-gray-400">Status Code</div>
                                    <div class="text-[13px] font-medium {{ $pageSnapshot->status_code === 200 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $pageSnapshot->status_code }}
                                    </div>
                                </div>
                                @endif
                            </div>

                            <div class="text-[11px] text-gray-300 pt-1">
                                Snapshot vom {{ $pageSnapshot->captured_at->format('d.m.Y') }}
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Keyword Rankings --}}
                    @if($keywordRankings->count())
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <h2 class="text-[13px] font-semibold text-gray-700 mb-3">Keyword Rankings</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-[12px]">
                                <thead>
                                    <tr class="text-left text-[10px] text-gray-400 uppercase tracking-wider border-b border-gray-100">
                                        <th class="pb-2 pr-3">Pos.</th>
                                        <th class="pb-2 pr-3">Keyword</th>
                                        <th class="pb-2 pr-3 text-right">Vol.</th>
                                        <th class="pb-2 pr-3 text-right">CPC</th>
                                        <th class="pb-2 text-right">KD</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($keywordRankings as $ranking)
                                    <tr class="border-b border-gray-50 hover:bg-gray-50/50">
                                        <td class="py-1.5 pr-3">
                                            <span class="inline-flex items-center justify-center w-6 h-5 rounded text-[11px] font-medium
                                                {{ $ranking->position <= 3 ? 'bg-green-50 text-green-700' : ($ranking->position <= 10 ? 'bg-blue-50 text-blue-700' : ($ranking->position <= 20 ? 'bg-yellow-50 text-yellow-700' : 'bg-gray-100 text-gray-500')) }}">
                                                {{ $ranking->position }}
                                            </span>
                                            @if($ranking->position_delta !== null)
                                                <span class="text-[10px] ml-0.5 {{ $ranking->position_delta > 0 ? 'text-green-500' : ($ranking->position_delta < 0 ? 'text-red-500' : 'text-gray-300') }}">
                                                    {{ $ranking->position_delta > 0 ? '+' . $ranking->position_delta : ($ranking->position_delta < 0 ? $ranking->position_delta : '=') }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-1.5 pr-3 text-gray-900">{{ $ranking->keyword?->keyword }}</td>
                                        <td class="py-1.5 pr-3 text-right text-gray-500">
                                            @if($ranking->keyword?->search_volume)
                                                {{ number_format($ranking->keyword->search_volume) }}
                                            @else
                                                <span class="text-gray-300">&mdash;</span>
                                            @endif
                                        </td>
                                        <td class="py-1.5 pr-3 text-right text-gray-500">
                                            @if($ranking->keyword?->cpc_cents)
                                                &euro;{{ number_format($ranking->keyword->cpc_cents / 100, 2) }}
                                            @else
                                                <span class="text-gray-300">&mdash;</span>
                                            @endif
                                        </td>
                                        <td class="py-1.5 text-right text-gray-500">
                                            @if($ranking->keyword?->keyword_difficulty)
                                                {{ $ranking->keyword->keyword_difficulty }}
                                            @else
                                                <span class="text-gray-300">&mdash;</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif

                    {{-- Page Changes --}}
                    @if($recentChanges->count())
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <h2 class="text-[13px] font-semibold text-gray-700 mb-3">Erkannte Seitenänderungen</h2>
                        <div class="space-y-2">
                            @foreach($recentChanges as $change)
                            <div class="flex items-start gap-3 p-2 rounded-lg hover:bg-gray-50 text-[12px]">
                                <div class="flex-shrink-0 mt-0.5">
                                    @if($change->severity === 'major')
                                        <span class="inline-flex w-2 h-2 rounded-full bg-red-400"></span>
                                    @elseif($change->severity === 'moderate')
                                        <span class="inline-flex w-2 h-2 rounded-full bg-yellow-400"></span>
                                    @else
                                        <span class="inline-flex w-2 h-2 rounded-full bg-blue-300"></span>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-900">{{ str_replace('_', ' ', ucfirst($change->change_type)) }}</span>
                                        <span class="text-[10px] px-1.5 py-0.5 rounded font-medium
                                            {{ $change->severity === 'major' ? 'bg-red-50 text-red-600' : ($change->severity === 'moderate' ? 'bg-yellow-50 text-yellow-600' : 'bg-blue-50 text-blue-600') }}">
                                            {{ $change->severity }}
                                        </span>
                                        <span class="text-gray-400 text-[11px]">{{ $change->detected_at->format('d.m.Y') }}</span>
                                    </div>
                                    @if($change->old_value || $change->new_value)
                                    <div class="mt-1 text-[11px]">
                                        @if($change->old_value)
                                            <div class="text-red-500 line-through truncate">&minus; {{ \Illuminate\Support\Str::limit($change->old_value, 120) }}</div>
                                        @endif
                                        @if($change->new_value)
                                            <div class="text-green-600 truncate">+ {{ \Illuminate\Support\Str::limit($change->new_value, 120) }}</div>
                                        @endif
                                    </div>
                                    @endif
                                    @if($change->delta !== null)
                                    <div class="mt-0.5 text-[11px] text-gray-400">
                                        Delta: <span class="{{ $change->delta > 0 ? 'text-green-600' : 'text-red-500' }}">{{ $change->delta > 0 ? '+' : '' }}{{ $change->delta }}</span>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Stammdaten --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <h2 class="text-[13px] font-semibold text-gray-700 mb-3">Stammdaten</h2>
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-[13px]">
                            <div>
                                <dt class="text-gray-400">Slug</dt>
                                <dd class="text-gray-900 font-mono">{{ $entity->slug }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400">Status</dt>
                                <dd>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium
                                        {{ $entity->status === 'aktiv' ? 'bg-green-50 text-green-700' : ($entity->status === 'saisonal_geschlossen' ? 'bg-yellow-50 text-yellow-700' : 'bg-red-50 text-red-700') }}">
                                        {{ str_replace('_', ' ', ucfirst($entity->status)) }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-400">Saison</dt>
                                <dd class="text-gray-900">{{ str_replace('_', ' ', ucfirst($entity->season)) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400">Quelle</dt>
                                <dd class="text-gray-900">{{ str_replace('_', ' ', ucfirst($entity->source)) }}</dd>
                            </div>
                            @if($entity->latitude && $entity->longitude)
                            <div>
                                <dt class="text-gray-400">Koordinaten</dt>
                                <dd class="text-gray-900 font-mono text-[12px]">{{ $entity->latitude }}, {{ $entity->longitude }}</dd>
                            </div>
                            @endif
                        </dl>
                        @if($entity->description)
                        <div class="mt-4 pt-3 border-t border-gray-100">
                            <p class="text-[13px] text-gray-600">{{ $entity->description }}</p>
                        </div>
                        @endif
                    </div>

                    {{-- Extra Fields --}}
                    @if($entity->extra_fields && count($entity->extra_fields))
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <h2 class="text-[13px] font-semibold text-gray-700 mb-3">Typ-spezifische Felder</h2>
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-[13px]">
                            @foreach($entity->extra_fields as $key => $value)
                            <div>
                                <dt class="text-gray-400">{{ str_replace('_', ' ', ucfirst($key)) }}</dt>
                                <dd class="text-gray-900">
                                    @if(is_array($value)) {{ implode(', ', $value) }}
                                    @elseif(is_bool($value)) {{ $value ? 'Ja' : 'Nein' }}
                                    @else {{ $value }}
                                    @endif
                                </dd>
                            </div>
                            @endforeach
                        </dl>
                    </div>
                    @endif
                </div>

                {{-- Sidebar --}}
                <div class="space-y-6">

                    {{-- Online-Präsenzen --}}
                    @if($entity->entityUrls->count())
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <h2 class="text-[13px] font-semibold text-gray-700 mb-3">Online-Präsenzen</h2>
                        <div class="space-y-2">
                            @foreach($entity->entityUrls as $entityUrl)
                            <div class="flex items-start gap-3 p-2 rounded-lg {{ $entityUrl->is_primary ? 'bg-blue-50 border border-blue-100' : 'hover:bg-gray-50' }}">
                                {{-- Platform Icon --}}
                                <div class="flex-shrink-0 mt-0.5">
                                    @switch($entityUrl->platform)
                                        @case('website')
                                            <svg class="w-4 h-4 {{ $entityUrl->is_primary ? 'text-blue-600' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" /></svg>
                                            @break
                                        @case('google_maps')
                                            <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                                            @break
                                        @case('tripadvisor')
                                            <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/></svg>
                                            @break
                                        @case('instagram')
                                            <svg class="w-4 h-4 text-pink-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                                            @break
                                        @case('facebook')
                                            <svg class="w-4 h-4 text-blue-700" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                            @break
                                        @case('booking')
                                            <svg class="w-4 h-4 text-blue-800" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21" /></svg>
                                            @break
                                        @case('yelp')
                                            <svg class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                                            @break
                                        @default
                                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" /></svg>
                                    @endswitch
                                </div>

                                {{-- URL Info --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600">
                                            {{ str_replace('_', ' ', ucfirst($entityUrl->platform)) }}
                                        </span>
                                        @if($entityUrl->is_primary)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-100 text-blue-700">Primary</span>
                                        @endif
                                    </div>
                                    <a href="{{ $entityUrl->url }}" target="_blank" rel="noopener" class="block text-[12px] text-blue-600 hover:text-blue-800 truncate mt-0.5" title="{{ $entityUrl->url }}">
                                        {{ $entityUrl->url }}
                                    </a>

                                    {{-- Snapshot metrics --}}
                                    @if($entityUrl->latestSnapshot)
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-1.5 text-[11px] text-gray-400">
                                            <span>{{ $entityUrl->latestSnapshot->captured_at->format('d.m.Y') }}</span>
                                            @if($entityUrl->latestSnapshot->keywords_count)
                                                <span>{{ $entityUrl->latestSnapshot->keywords_count }} KW</span>
                                            @endif
                                            @if($entityUrl->latestSnapshot->organic_traffic_estimate)
                                                <span>~{{ number_format($entityUrl->latestSnapshot->organic_traffic_estimate) }} Traffic</span>
                                            @endif
                                            @if($entityUrl->latestSnapshot->organic_value_cents)
                                                <span>&euro;{{ number_format($entityUrl->latestSnapshot->organic_value_cents / 100, 0) }}</span>
                                            @endif
                                            @if($entityUrl->latestSnapshot->domain_authority)
                                                <span>DA {{ $entityUrl->latestSnapshot->domain_authority }}</span>
                                            @endif
                                        </div>
                                    @elseif($entityUrl->last_checked_at)
                                        <div class="text-[11px] text-gray-300 mt-1">Entdeckt {{ $entityUrl->last_checked_at->format('d.m.Y') }}</div>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Outgoing Relationships --}}
                    @if($entity->outgoingRelationships->count())
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <h2 class="text-[13px] font-semibold text-gray-700 mb-3">Beziehungen (ausgehend)</h2>
                        <div class="space-y-2">
                            @foreach($entity->outgoingRelationships as $rel)
                            <a href="{{ route('syltjunkie.entity.detail', $rel->targetEntity) }}" class="flex items-center gap-2 text-[13px] hover:bg-gray-50 rounded p-1.5 -mx-1.5">
                                <span class="text-gray-400">{{ $rel->relationType?->name }}</span>
                                <span class="text-gray-900 font-medium">{{ $rel->targetEntity?->name }}</span>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Incoming Relationships --}}
                    @if($entity->incomingRelationships->count())
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <h2 class="text-[13px] font-semibold text-gray-700 mb-3">Beziehungen (eingehend)</h2>
                        <div class="space-y-2">
                            @foreach($entity->incomingRelationships as $rel)
                            <a href="{{ route('syltjunkie.entity.detail', $rel->sourceEntity) }}" class="flex items-center gap-2 text-[13px] hover:bg-gray-50 rounded p-1.5 -mx-1.5">
                                <span class="text-gray-400">{{ $rel->relationType?->inverse_name ?? $rel->relationType?->name }}</span>
                                <span class="text-gray-900 font-medium">{{ $rel->sourceEntity?->name }}</span>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
