<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Rankings'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Rankings</h1>
            </div>

            {{-- Filters --}}
            <div class="flex items-center gap-3">
                <div class="flex-1">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Keyword suchen..."
                        class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                    />
                </div>
                <select wire:model.live="filterDevice" class="rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                    <option value="">Alle Devices</option>
                    <option value="desktop">Desktop</option>
                    <option value="mobile">Mobile</option>
                </select>
            </div>

            {{-- Rankings Table --}}
            @if($rankings->count())
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th wire:click="sortBy('position')" class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700">
                                Position
                                @if($sortField === 'position') <span>{{ $sortDir === 'asc' ? '&uarr;' : '&darr;' }}</span> @endif
                            </th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Keyword</th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Entity</th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">URL</th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">SV</th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Delta</th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Device</th>
                            <th wire:click="sortBy('captured_at')" class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700">
                                Erfasst
                                @if($sortField === 'captured_at') <span>{{ $sortDir === 'asc' ? '&uarr;' : '&darr;' }}</span> @endif
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($rankings as $ranking)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-md text-[13px] font-bold tabular-nums
                                    {{ $ranking->position <= 3 ? 'bg-green-50 text-green-700' : ($ranking->position <= 10 ? 'bg-blue-50 text-blue-700' : ($ranking->position <= 20 ? 'bg-yellow-50 text-yellow-700' : 'bg-gray-50 text-gray-600')) }}">
                                    {{ $ranking->position }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-[13px] font-medium text-gray-900">
                                {{ $ranking->keyword?->keyword }}
                            </td>
                            <td class="px-4 py-3 text-[13px]">
                                @if($ranking->entityUrl?->entity)
                                    <a href="{{ route('syltjunkie.entity.detail', $ranking->entityUrl->entity) }}" class="text-blue-600 hover:text-blue-800">{{ $ranking->entityUrl->entity->name }}</a>
                                @else
                                    <span class="text-gray-400">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-500 max-w-[200px] truncate">
                                {{ $ranking->ranked_url }}
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-500 tabular-nums">
                                {{ $ranking->keyword?->search_volume ? number_format($ranking->keyword->search_volume) : '&mdash;' }}
                            </td>
                            <td class="px-4 py-3 text-[13px] tabular-nums">
                                @if($ranking->position_delta !== null)
                                    <span class="{{ $ranking->position_delta > 0 ? 'text-green-600' : ($ranking->position_delta < 0 ? 'text-red-600' : 'text-gray-400') }}">
                                        {{ $ranking->position_delta > 0 ? '+' : '' }}{{ $ranking->position_delta }}
                                    </span>
                                @else
                                    <span class="text-[11px] text-blue-500">neu</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-500">
                                {{ $ranking->device ?? '&mdash;' }}
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-500">
                                {{ $ranking->captured_at?->format('d.m.Y') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $rankings->links() }}
            </div>
            @else
            <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                    @svg('heroicon-o-chart-bar', 'w-6 h-6 text-gray-400')
                </div>
                <h3 class="text-[13px] font-semibold text-gray-700 mb-1">Keine Rankings</h3>
                <p class="text-[12px] text-gray-400">Nutze den Snapshot-Command, um Keyword-Rankings zu erfassen.</p>
            </div>
            @endif
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
