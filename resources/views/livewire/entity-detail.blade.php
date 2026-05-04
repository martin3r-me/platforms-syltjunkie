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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Main Info --}}
                <div class="lg:col-span-2 space-y-6">
                    {{-- Base Fields --}}
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

                    {{-- Entity URLs --}}
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

                                    {{-- Latest Snapshot --}}
                                    @if($entityUrl->snapshots->isNotEmpty())
                                        @php $snap = $entityUrl->snapshots->first(); @endphp
                                        <div class="flex items-center gap-3 mt-1.5 text-[11px] text-gray-400">
                                            <span title="Snapshot vom">{{ $snap->captured_at->format('d.m.Y') }}</span>
                                            @if($snap->keywords && count($snap->keywords))
                                                <span title="Keywords">{{ count($snap->keywords) }} Keywords</span>
                                            @endif
                                            @if($snap->organic_traffic_estimate)
                                                <span title="Org. Traffic">~{{ number_format($snap->organic_traffic_estimate) }} Traffic</span>
                                            @endif
                                            @if($snap->domain_authority)
                                                <span title="Domain Authority">DA {{ $snap->domain_authority }}</span>
                                            @endif
                                            @if($snap->backlinks_count)
                                                <span title="Backlinks">{{ number_format($snap->backlinks_count) }} BL</span>
                                            @endif
                                        </div>
                                    @elseif($entityUrl->last_checked_at)
                                        <div class="text-[11px] text-gray-300 mt-1">Entdeckt {{ $entityUrl->last_checked_at->format('d.m.Y') }} &middot; Kein Snapshot</div>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Relationships Sidebar --}}
                <div class="space-y-6">
                    {{-- Outgoing --}}
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

                    {{-- Incoming --}}
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
