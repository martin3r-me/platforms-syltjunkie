<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Shop'],
            ['label' => 'Bestellungen', 'href' => route('syltjunkie.shop.orders.index')],
            ['label' => $order->order_number],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-4">
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 text-[13px] px-3 py-2 rounded-md">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-3 gap-6">
                {{-- Main Column (2/3) --}}
                <div class="col-span-2 space-y-4">
                    {{-- Order Header --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h1 class="text-xl font-semibold text-gray-900 font-mono">{{ $order->order_number }}</h1>
                                <p class="text-[12px] text-gray-400 mt-0.5">{{ $order->created_at->format('d.m.Y H:i') }}</p>
                            </div>
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
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-[12px] font-medium {{ $statusColors[$order->status] ?? 'bg-gray-100 text-gray-500' }}">
                                {{ $statusLabels[$order->status] ?? $order->status }}
                            </span>
                        </div>

                        {{-- Status Actions --}}
                        <div class="flex items-center gap-2 border-t border-gray-100 pt-3">
                            @if($order->status === 'pending')
                                <button wire:click="updateStatus('paid')"
                                        class="px-3 py-1.5 text-[12px] font-medium rounded-md bg-blue-600 text-white hover:bg-blue-700 transition-colors">
                                    Als bezahlt markieren
                                </button>
                                <button wire:click="updateStatus('cancelled')"
                                        wire:confirm="Bestellung wirklich stornieren?"
                                        class="px-3 py-1.5 text-[12px] font-medium rounded-md border border-red-300 text-red-600 hover:bg-red-50 transition-colors">
                                    Stornieren
                                </button>
                            @elseif($order->status === 'paid')
                                <button wire:click="updateStatus('shipped')"
                                        class="px-3 py-1.5 text-[12px] font-medium rounded-md bg-indigo-600 text-white hover:bg-indigo-700 transition-colors">
                                    Als versendet markieren
                                </button>
                                <button wire:click="updateStatus('refunded')"
                                        wire:confirm="Bestellung wirklich erstatten?"
                                        class="px-3 py-1.5 text-[12px] font-medium rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors">
                                    Erstatten
                                </button>
                            @elseif($order->status === 'shipped')
                                <button wire:click="updateStatus('completed')"
                                        class="px-3 py-1.5 text-[12px] font-medium rounded-md bg-green-600 text-white hover:bg-green-700 transition-colors">
                                    Als abgeschlossen markieren
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Order Items --}}
                    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Positionen</h2>
                        </div>
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Produkt</th>
                                    <th class="px-4 py-2 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Menge</th>
                                    <th class="px-4 py-2 text-right text-[11px] font-medium text-gray-500 uppercase tracking-wider">Stückpreis</th>
                                    <th class="px-4 py-2 text-right text-[11px] font-medium text-gray-500 uppercase tracking-wider">Gesamt</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($order->items as $item)
                                    <tr>
                                        <td class="px-4 py-2.5">
                                            <div class="text-[13px] font-medium text-gray-900">
                                                {{ $item->product_name }}
                                                @if($item->is_digital)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-medium bg-purple-50 text-purple-600 ml-1">Digital</span>
                                                @endif
                                            </div>
                                            @if($item->variant_label)
                                                <div class="text-[11px] text-gray-400">{{ $item->variant_label }}</div>
                                            @endif
                                            @if($item->download_url)
                                                <a href="{{ $item->download_url }}" target="_blank" class="text-[11px] text-blue-600 hover:text-blue-800">
                                                    Download-Link
                                                </a>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5 text-center text-[13px] text-gray-600">
                                            {{ $item->quantity }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-[13px] text-gray-600">
                                            {{ number_format($item->unit_price_cents / 100, 2, ',', '.') }} €
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-[13px] font-medium text-gray-900">
                                            {{ number_format($item->total_cents / 100, 2, ',', '.') }} €
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="3" class="px-4 py-2 text-right text-[12px] text-gray-500">Zwischensumme</td>
                                    <td class="px-4 py-2 text-right text-[13px] text-gray-700">{{ number_format($order->subtotal_cents / 100, 2, ',', '.') }} €</td>
                                </tr>
                                @if($order->shipping_cents > 0)
                                    <tr>
                                        <td colspan="3" class="px-4 py-1.5 text-right text-[12px] text-gray-500">Versand</td>
                                        <td class="px-4 py-1.5 text-right text-[13px] text-gray-700">{{ number_format($order->shipping_cents / 100, 2, ',', '.') }} €</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td colspan="3" class="px-4 py-2 text-right text-[13px] font-semibold text-gray-900">Gesamt</td>
                                    <td class="px-4 py-2 text-right text-[14px] font-bold text-gray-900">{{ $order->formatted_total }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- Notes --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                        <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Interne Notizen</h2>
                        <textarea wire:model="notes" rows="4"
                                  class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"></textarea>
                        <button wire:click="saveNotes"
                                class="px-3 py-1.5 text-[12px] font-medium rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200 transition-colors">
                            Notizen speichern
                        </button>
                    </div>
                </div>

                {{-- Sidebar Column (1/3) --}}
                <div class="space-y-4">
                    {{-- Customer Info --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                        <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Kunde</h2>
                        <div class="space-y-1.5">
                            <div class="text-[13px] font-medium text-gray-900">{{ $order->customer_name }}</div>
                            <div class="text-[12px] text-gray-500">{{ $order->customer_email }}</div>
                        </div>
                    </div>

                    {{-- Shipping Address --}}
                    @if($order->shipping_address)
                        <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                            <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Lieferadresse</h2>
                            <div class="text-[12px] text-gray-600 space-y-0.5">
                                <div>{{ $order->shipping_address['street'] ?? '' }}</div>
                                <div>{{ $order->shipping_address['zip'] ?? '' }} {{ $order->shipping_address['city'] ?? '' }}</div>
                                <div>{{ $order->shipping_address['country'] ?? '' }}</div>
                            </div>
                        </div>
                    @endif

                    {{-- Payment Info --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                        <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Zahlung</h2>
                        <div class="space-y-1.5 text-[12px]">
                            @if($order->payment_provider)
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Provider</span>
                                    <span class="text-gray-900 font-medium">{{ $order->payment_provider }}</span>
                                </div>
                            @endif
                            @if($order->payment_reference)
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Referenz</span>
                                    <span class="text-gray-900 font-mono text-[11px]">{{ $order->payment_reference }}</span>
                                </div>
                            @endif
                            @if($order->paid_at)
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Bezahlt am</span>
                                    <span class="text-gray-900">{{ $order->paid_at->format('d.m.Y H:i') }}</span>
                                </div>
                            @endif
                            @if($order->shipped_at)
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Versendet am</span>
                                    <span class="text-gray-900">{{ $order->shipped_at->format('d.m.Y H:i') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Timeline --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                        <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Zeitverlauf</h2>
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-gray-400"></div>
                                <span class="text-[12px] text-gray-600">Erstellt: {{ $order->created_at->format('d.m.Y H:i') }}</span>
                            </div>
                            @if($order->paid_at)
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                    <span class="text-[12px] text-gray-600">Bezahlt: {{ $order->paid_at->format('d.m.Y H:i') }}</span>
                                </div>
                            @endif
                            @if($order->shipped_at)
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full bg-indigo-500"></div>
                                    <span class="text-[12px] text-gray-600">Versendet: {{ $order->shipped_at->format('d.m.Y H:i') }}</span>
                                </div>
                            @endif
                            @if($order->status === 'completed')
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full bg-green-500"></div>
                                    <span class="text-[12px] text-gray-600">Abgeschlossen</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
