<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Bewertungen'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Bewertungen</h1>
            </div>

            {{-- Stats --}}
            @if($entities->count())
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Entities</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $entities->count() }}</div>
                    <div class="text-[11px] text-gray-400 mt-1">Mit Google-Bewertungen</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Durchschnitt</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $avgRating ? number_format($avgRating, 1) : '&mdash;' }}</div>
                    <div class="text-[11px] text-gray-400 mt-1">Rating</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Reviews</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($totalReviews) }}</div>
                    <div class="text-[11px] text-gray-400 mt-1">Gesamt</div>
                </div>
            </div>
            @endif

            {{-- Entities Table --}}
            @if($entities->count())
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Entity</th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Typ</th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Ort</th>
                            <th wire:click="sortBy('rating')" class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700">
                                Rating
                                @if($sortField === 'rating') <span>{{ $sortDir === 'asc' ? '&uarr;' : '&darr;' }}</span> @endif
                            </th>
                            <th wire:click="sortBy('reviews')" class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700">
                                Reviews
                                @if($sortField === 'reviews') <span>{{ $sortDir === 'asc' ? '&uarr;' : '&darr;' }}</span> @endif
                            </th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Zuletzt</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($entities as $entity)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('syltjunkie.entity.detail', $entity) }}" class="text-[13px] font-medium text-blue-600 hover:text-blue-800">{{ $entity->name }}</a>
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-500">
                                {{ $entity->entityType?->name }}
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-500">
                                {{ $entity->ort }}
                            </td>
                            <td class="px-4 py-3">
                                @if($entity->latest_rating)
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-[13px] font-bold tabular-nums {{ $entity->latest_rating >= 4.5 ? 'text-green-600' : ($entity->latest_rating >= 4.0 ? 'text-blue-600' : ($entity->latest_rating >= 3.0 ? 'text-yellow-600' : 'text-red-600')) }}">
                                            {{ number_format($entity->latest_rating, 1) }}
                                        </span>
                                        <div class="flex gap-px">
                                            @for($i = 1; $i <= 5; $i++)
                                                @if($i <= round($entity->latest_rating))
                                                    @svg('heroicon-s-star', 'w-3 h-3 text-yellow-400')
                                                @else
                                                    @svg('heroicon-o-star', 'w-3 h-3 text-gray-300')
                                                @endif
                                            @endfor
                                        </div>
                                    </div>
                                @else
                                    <span class="text-[11px] text-gray-400">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[13px] tabular-nums text-gray-700">
                                {{ $entity->latest_review_count ? number_format($entity->latest_review_count) : '&mdash;' }}
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-500">
                                {{ $entity->snapshot_date?->format('d.m.Y') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                    @svg('heroicon-o-map-pin', 'w-6 h-6 text-gray-400')
                </div>
                <h3 class="text-[13px] font-semibold text-gray-700 mb-1">Keine Bewertungen</h3>
                <p class="text-[12px] text-gray-400">Nutze Google Business Fetch, um Bewertungsdaten zu erfassen.</p>
            </div>
            @endif
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
