<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Entities'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Entities</h1>
            </div>

            {{-- Filters --}}
            <div class="flex items-center gap-3">
                <div class="flex-1">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Suche nach Name, Ort, Slug..."
                        class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                    />
                </div>
                <select wire:model.live="filterGroupId" class="rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                    <option value="">Alle Gruppen</option>
                    @foreach($groups as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="filterStatus" class="rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                    <option value="">Alle Status</option>
                    <option value="aktiv">Aktiv</option>
                    <option value="saisonal_geschlossen">Saisonal geschlossen</option>
                    <option value="dauerhaft_geschlossen">Dauerhaft geschlossen</option>
                </select>
            </div>

            {{-- Entity Table --}}
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">
                                    <button wire:click="sortBy('name')" class="flex items-center gap-1 hover:text-gray-900">
                                        Entity
                                        @if($sortField === 'name')
                                            @svg($sortDir === 'asc' ? 'heroicon-s-chevron-up' : 'heroicon-s-chevron-down', 'w-3 h-3')
                                        @endif
                                    </button>
                                </th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">
                                    Ort
                                </th>
                                <th class="px-4 py-2.5 text-right text-[11px] font-medium text-gray-500 uppercase tracking-wider">Keywords</th>
                                <th class="px-4 py-2.5 text-right text-[11px] font-medium text-gray-500 uppercase tracking-wider">Traffic</th>
                                <th class="px-4 py-2.5 text-right text-[11px] font-medium text-gray-500 uppercase tracking-wider">Wert</th>
                                <th class="px-4 py-2.5 text-right text-[11px] font-medium text-gray-500 uppercase tracking-wider">OnPage</th>
                                <th class="px-4 py-2.5 text-right text-[11px] font-medium text-gray-500 uppercase tracking-wider">Words</th>
                                <th class="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">
                                    <button wire:click="sortBy('status')" class="flex items-center gap-1 hover:text-gray-900 mx-auto">
                                        Status
                                        @if($sortField === 'status')
                                            @svg($sortDir === 'asc' ? 'heroicon-s-chevron-up' : 'heroicon-s-chevron-down', 'w-3 h-3')
                                        @endif
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($entities as $entity)
                                @php
                                    $websiteUrl = $entity->entityUrls->firstWhere('platform', 'website');
                                    $urlSnapshot = $websiteUrl?->latestSnapshot;
                                    $pageSnapshot = $websiteUrl?->latestPageSnapshot;
                                @endphp
                                <tr class="hover:bg-gray-50 transition-colors cursor-pointer"
                                    onclick="window.location='{{ route('syltjunkie.entity.detail', $entity) }}'">
                                    {{-- Entity Name + Type --}}
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-7 h-7 rounded-md flex items-center justify-center flex-shrink-0"
                                                 style="background-color: {{ $entity->entityType?->color ?? '#3B82F6' }}15; color: {{ $entity->entityType?->color ?? '#3B82F6' }};">
                                                @svg('heroicon-o-building-storefront', 'w-3.5 h-3.5')
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-[13px] font-medium text-gray-900 truncate">{{ $entity->name }}</div>
                                                <div class="text-[11px] text-gray-400 truncate">
                                                    {{ $entity->entityType?->group?->name }} &rarr; {{ $entity->entityType?->name }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Ort --}}
                                    <td class="px-4 py-2.5 text-[13px] text-gray-600">
                                        {{ $entity->outgoingRelationships->first()?->targetEntity?->name ?? '—' }}
                                    </td>

                                    {{-- Keywords --}}
                                    <td class="px-4 py-2.5 text-right">
                                        @if($urlSnapshot && $urlSnapshot->keywords_count)
                                            <span class="text-[13px] font-medium text-gray-900">{{ number_format($urlSnapshot->keywords_count) }}</span>
                                        @else
                                            <span class="text-[11px] text-gray-300">—</span>
                                        @endif
                                    </td>

                                    {{-- Organic Traffic --}}
                                    <td class="px-4 py-2.5 text-right">
                                        @if($urlSnapshot && $urlSnapshot->organic_traffic_estimate)
                                            <span class="text-[13px] font-medium text-gray-900">~{{ number_format($urlSnapshot->organic_traffic_estimate) }}</span>
                                        @else
                                            <span class="text-[11px] text-gray-300">—</span>
                                        @endif
                                    </td>

                                    {{-- Traffic Value --}}
                                    <td class="px-4 py-2.5 text-right">
                                        @if($urlSnapshot && $urlSnapshot->organic_value_cents)
                                            <span class="text-[13px] font-medium text-gray-900">&euro;{{ number_format($urlSnapshot->organic_value_cents / 100, 0, ',', '.') }}</span>
                                        @else
                                            <span class="text-[11px] text-gray-300">—</span>
                                        @endif
                                    </td>

                                    {{-- OnPage Score --}}
                                    <td class="px-4 py-2.5 text-right">
                                        @if($pageSnapshot && $pageSnapshot->onpage_score !== null)
                                            @php
                                                $score = $pageSnapshot->onpage_score;
                                                $scoreColor = $score >= 80 ? 'text-green-600' : ($score >= 50 ? 'text-yellow-600' : 'text-red-600');
                                            @endphp
                                            <span class="text-[13px] font-medium {{ $scoreColor }}">{{ number_format($score, 0) }}</span>
                                        @else
                                            <span class="text-[11px] text-gray-300">—</span>
                                        @endif
                                    </td>

                                    {{-- Word Count --}}
                                    <td class="px-4 py-2.5 text-right">
                                        @if($pageSnapshot && $pageSnapshot->word_count)
                                            <span class="text-[13px] text-gray-600">{{ number_format($pageSnapshot->word_count) }}</span>
                                        @else
                                            <span class="text-[11px] text-gray-300">—</span>
                                        @endif
                                    </td>

                                    {{-- Status --}}
                                    <td class="px-4 py-2.5 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium
                                            {{ $entity->status === 'aktiv' ? 'bg-green-50 text-green-700' : ($entity->status === 'saisonal_geschlossen' ? 'bg-yellow-50 text-yellow-700' : 'bg-red-50 text-red-700') }}">
                                            {{ str_replace('_', ' ', ucfirst($entity->status)) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-[13px] text-gray-400">
                                        Keine Entities gefunden.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $entities->links() }}
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
