<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Shop'],
            ['label' => 'Bestellungen'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Bestellungen</h1>
            </div>

            {{-- Filters --}}
            <div class="flex items-center gap-3">
                <div class="flex-1">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Suche nach Bestellnummer, Name, E-Mail..."
                        class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                    />
                </div>
                <select wire:model.live="filterStatus" class="rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                    <option value="">Alle Status</option>
                    <option value="pending">Ausstehend</option>
                    <option value="paid">Bezahlt</option>
                    <option value="shipped">Versendet</option>
                    <option value="completed">Abgeschlossen</option>
                    <option value="cancelled">Storniert</option>
                    <option value="refunded">Erstattet</option>
                </select>
            </div>

            {{-- Orders Table --}}
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Bestellung</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Kunde</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Datum</th>
                                <th class="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Positionen</th>
                                <th class="px-4 py-2.5 text-right text-[11px] font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-2.5 text-right text-[11px] font-medium text-gray-500 uppercase tracking-wider">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($orders as $order)
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-50 text-yellow-700',
                                        'paid' => 'bg-blue-50 text-blue-700',
                                        'shipped' => 'bg-indigo-50 text-indigo-700',
                                        'completed' => 'bg-green-50 text-green-700',
                                        'cancelled' => 'bg-red-50 text-red-700',
                                        'refunded' => 'bg-gray-100 text-gray-500',
                                    ];
                                    $statusLabels = [
                                        'pending' => 'Ausstehend',
                                        'paid' => 'Bezahlt',
                                        'shipped' => 'Versendet',
                                        'completed' => 'Abgeschlossen',
                                        'cancelled' => 'Storniert',
                                        'refunded' => 'Erstattet',
                                    ];
                                @endphp
                                <tr class="hover:bg-gray-50 transition-colors cursor-pointer"
                                    onclick="window.location='{{ route('syltjunkie.shop.orders.detail', $order) }}'">
                                    {{-- Order Number --}}
                                    <td class="px-4 py-2.5">
                                        <span class="text-[13px] font-medium text-gray-900 font-mono">{{ $order->order_number }}</span>
                                    </td>

                                    {{-- Customer --}}
                                    <td class="px-4 py-2.5">
                                        <div class="text-[13px] text-gray-900">{{ $order->customer_name }}</div>
                                        <div class="text-[11px] text-gray-400">{{ $order->customer_email }}</div>
                                    </td>

                                    {{-- Date --}}
                                    <td class="px-4 py-2.5 text-[13px] text-gray-600">
                                        {{ $order->created_at->format('d.m.Y H:i') }}
                                    </td>

                                    {{-- Item Count --}}
                                    <td class="px-4 py-2.5 text-center text-[13px] text-gray-600">
                                        {{ $order->items_count }}
                                    </td>

                                    {{-- Total --}}
                                    <td class="px-4 py-2.5 text-right text-[13px] font-medium text-gray-900">
                                        {{ $order->formatted_total }}
                                    </td>

                                    {{-- Status --}}
                                    <td class="px-4 py-2.5 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium {{ $statusColors[$order->status] ?? 'bg-gray-100 text-gray-500' }}">
                                            {{ $statusLabels[$order->status] ?? $order->status }}
                                        </span>
                                    </td>

                                    {{-- Quick Actions --}}
                                    <td class="px-4 py-2.5 text-right" onclick="event.stopPropagation()">
                                        <div class="flex items-center justify-end gap-1">
                                            @if($order->status === 'pending')
                                                <button wire:click="updateStatus({{ $order->id }}, 'paid')"
                                                        class="px-2 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 hover:bg-blue-100" title="Als bezahlt markieren">
                                                    Bezahlt
                                                </button>
                                            @elseif($order->status === 'paid')
                                                <button wire:click="updateStatus({{ $order->id }}, 'shipped')"
                                                        class="px-2 py-0.5 rounded text-[10px] font-medium bg-indigo-50 text-indigo-700 hover:bg-indigo-100" title="Als versendet markieren">
                                                    Versendet
                                                </button>
                                            @elseif($order->status === 'shipped')
                                                <button wire:click="updateStatus({{ $order->id }}, 'completed')"
                                                        class="px-2 py-0.5 rounded text-[10px] font-medium bg-green-50 text-green-700 hover:bg-green-100" title="Als abgeschlossen markieren">
                                                    Abgeschlossen
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-[13px] text-gray-400">
                                        Keine Bestellungen gefunden.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $orders->links() }}
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
