<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Shop'],
            ['label' => 'Produkte'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Produkte</h1>
                <a href="{{ route('syltjunkie.shop.products.create') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-[13px] font-medium rounded-md hover:bg-blue-700 transition-colors">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    Neues Produkt
                </a>
            </div>

            {{-- Filters --}}
            <div class="flex items-center gap-3">
                <div class="flex-1">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Suche nach Name..."
                        class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                    />
                </div>
                <select wire:model.live="filterStatus" class="rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                    <option value="">Alle Status</option>
                    <option value="draft">Entwurf</option>
                    <option value="active">Aktiv</option>
                    <option value="archived">Archiviert</option>
                </select>
                <select wire:model.live="filterType" class="rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                    <option value="">Alle Typen</option>
                    <option value="physical">Physisch</option>
                    <option value="digital">Digital</option>
                </select>
            </div>

            {{-- Product Table --}}
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Produkt</th>
                                <th class="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Typ</th>
                                <th class="px-4 py-2.5 text-right text-[11px] font-medium text-gray-500 uppercase tracking-wider">Preis</th>
                                <th class="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Varianten</th>
                                <th class="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Bestand</th>
                                <th class="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-2.5 text-right text-[11px] font-medium text-gray-500 uppercase tracking-wider">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($products as $product)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    {{-- Product Name + Image --}}
                                    <td class="px-4 py-2.5">
                                        <a href="{{ route('syltjunkie.shop.products.edit', $product) }}" wire:navigate class="flex items-center gap-2.5">
                                            @php $img = $product->primaryImage->first(); @endphp
                                            @if($img)
                                                <img src="{{ $img->thumbnail_url }}" alt="" class="w-8 h-8 rounded object-cover flex-shrink-0" />
                                            @else
                                                <div class="w-8 h-8 rounded bg-gray-100 flex items-center justify-center flex-shrink-0">
                                                    @svg('heroicon-o-photo', 'w-4 h-4 text-gray-300')
                                                </div>
                                            @endif
                                            <div class="min-w-0">
                                                <div class="text-[13px] font-medium text-gray-900 truncate">{{ $product->name }}</div>
                                                <div class="text-[11px] text-gray-400 truncate">{{ $product->slug }}</div>
                                            </div>
                                        </a>
                                    </td>

                                    {{-- Type Badge --}}
                                    <td class="px-4 py-2.5 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium
                                            {{ $product->product_type === 'physical' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700' }}">
                                            {{ $product->product_type === 'physical' ? 'Physisch' : 'Digital' }}
                                        </span>
                                    </td>

                                    {{-- Price --}}
                                    <td class="px-4 py-2.5 text-right text-[13px] font-medium text-gray-900">
                                        {{ $product->formatted_price }}
                                    </td>

                                    {{-- Variants Count --}}
                                    <td class="px-4 py-2.5 text-center text-[13px] text-gray-600">
                                        {{ $product->variants_count ?: '—' }}
                                    </td>

                                    {{-- Stock --}}
                                    <td class="px-4 py-2.5 text-center">
                                        @if($product->product_type === 'digital')
                                            <span class="text-[11px] text-gray-400">∞</span>
                                        @elseif($product->stock_quantity === null)
                                            <span class="text-[11px] text-gray-400">∞</span>
                                        @else
                                            <span class="text-[13px] {{ $product->stock_quantity > 0 ? 'text-gray-600' : 'text-red-600 font-medium' }}">
                                                {{ $product->stock_quantity }}
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Status --}}
                                    <td class="px-4 py-2.5 text-center">
                                        @php
                                            $statusColors = [
                                                'draft' => 'bg-yellow-50 text-yellow-700',
                                                'active' => 'bg-green-50 text-green-700',
                                                'archived' => 'bg-gray-100 text-gray-500',
                                            ];
                                            $statusLabels = ['draft' => 'Entwurf', 'active' => 'Aktiv', 'archived' => 'Archiviert'];
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium {{ $statusColors[$product->status] ?? 'bg-gray-100 text-gray-500' }}">
                                            {{ $statusLabels[$product->status] ?? $product->status }}
                                        </span>
                                    </td>

                                    {{-- Actions --}}
                                    <td class="px-4 py-2.5 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <a href="{{ route('syltjunkie.shop.products.edit', $product) }}" wire:navigate
                                               class="p-1 rounded hover:bg-gray-100 text-gray-400 hover:text-gray-600" title="Bearbeiten">
                                                @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                            </a>
                                            <button wire:click="duplicateProduct({{ $product->id }})"
                                                    class="p-1 rounded hover:bg-gray-100 text-gray-400 hover:text-gray-600" title="Duplizieren">
                                                @svg('heroicon-o-document-duplicate', 'w-4 h-4')
                                            </button>
                                            @if($product->status !== 'archived')
                                                <button wire:click="archiveProduct({{ $product->id }})"
                                                        wire:confirm="Produkt wirklich archivieren?"
                                                        class="p-1 rounded hover:bg-gray-100 text-gray-400 hover:text-red-600" title="Archivieren">
                                                    @svg('heroicon-o-archive-box', 'w-4 h-4')
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-[13px] text-gray-400">
                                        Keine Produkte gefunden.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $products->links() }}
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
