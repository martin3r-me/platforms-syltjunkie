<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Trend Signals'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Trend Signals</h1>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-red-500 uppercase tracking-wide mb-1">Action</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $stats['action'] }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-yellow-500 uppercase tracking-wide mb-1">Watch</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $stats['watch'] }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-blue-500 uppercase tracking-wide mb-1">Info</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $stats['info'] }}</div>
                </div>
            </div>

            {{-- Filters --}}
            <div class="flex items-center gap-3">
                <select wire:model.live="filterSeverity" class="rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                    <option value="">Alle Severity</option>
                    <option value="action">Action</option>
                    <option value="watch">Watch</option>
                    <option value="info">Info</option>
                </select>
                <select wire:model.live="filterStatus" class="rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                    <option value="">Offen (new/acknowledged)</option>
                    <option value="new">New</option>
                    <option value="acknowledged">Acknowledged</option>
                    <option value="resolved">Resolved</option>
                </select>
                <select wire:model.live="filterType" class="rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                    <option value="">Alle Typen</option>
                    <option value="rating_drop">Rating Drop</option>
                    <option value="review_velocity">Review Velocity</option>
                    <option value="ranking_change">Ranking Change</option>
                    <option value="new_keyword">New Keyword</option>
                    <option value="volume_spike">Volume Spike</option>
                    <option value="trend_surge">Trend Surge</option>
                    <option value="keyword_opportunity">Keyword Opportunity</option>
                </select>
            </div>

            {{-- Signals Table --}}
            @if($signals->count())
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Severity</th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Signal</th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Entity</th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Typ</th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Metriken</th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Erkannt</th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($signals as $signal)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <span class="inline-flex px-1.5 py-0.5 rounded text-[11px] font-medium
                                    {{ $signal->severity === 'action' ? 'bg-red-50 text-red-600' : ($signal->severity === 'watch' ? 'bg-yellow-50 text-yellow-600' : 'bg-blue-50 text-blue-600') }}">
                                    {{ $signal->severity }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-[13px] font-medium text-gray-900">{{ $signal->title }}</div>
                                @if($signal->description)
                                    <div class="text-[11px] text-gray-400 mt-0.5">{{ Str::limit($signal->description, 80) }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[13px]">
                                @if($signal->entity)
                                    <a href="{{ route('syltjunkie.entity.detail', $signal->entity) }}" class="text-blue-600 hover:text-blue-800">{{ $signal->entity->name }}</a>
                                @else
                                    <span class="text-gray-400">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-500">
                                {{ str_replace('_', ' ', $signal->signal_type) }}
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-500 tabular-nums">
                                @if($signal->metric_before !== null && $signal->metric_after !== null)
                                    {{ number_format($signal->metric_before, 1) }} &rarr; {{ number_format($signal->metric_after, 1) }}
                                    @if($signal->metric_delta !== null)
                                        <span class="{{ $signal->metric_delta >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                            ({{ $signal->metric_delta >= 0 ? '+' : '' }}{{ number_format($signal->metric_delta, 1) }})
                                        </span>
                                    @endif
                                @else
                                    <span class="text-gray-400">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-500">
                                {{ $signal->detected_at->format('d.m.Y') }}
                            </td>
                            <td class="px-4 py-3">
                                <select
                                    wire:change="updateStatus({{ $signal->id }}, $event.target.value)"
                                    class="rounded border-gray-300 text-[11px] px-2 py-1"
                                >
                                    <option value="new" {{ $signal->status === 'new' ? 'selected' : '' }}>new</option>
                                    <option value="acknowledged" {{ $signal->status === 'acknowledged' ? 'selected' : '' }}>acknowledged</option>
                                    <option value="resolved" {{ $signal->status === 'resolved' ? 'selected' : '' }}>resolved</option>
                                </select>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $signals->links() }}
            </div>
            @else
            <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                    @svg('heroicon-o-signal', 'w-6 h-6 text-gray-400')
                </div>
                <h3 class="text-[13px] font-semibold text-gray-700 mb-1">Keine Trend Signals</h3>
                <p class="text-[12px] text-gray-400">Nutze den Snapshot-Command oder das MCP-Tool, um Trend Signals zu erkennen.</p>
            </div>
            @endif
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
