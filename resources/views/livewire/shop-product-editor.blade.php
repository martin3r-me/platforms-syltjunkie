<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Shop'],
            ['label' => 'Produkte', 'href' => route('syltjunkie.shop.products.index')],
            ['label' => $productId ? 'Bearbeiten' : 'Neu'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-4">
            {{-- Header --}}
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">
                    {{ $productId ? 'Produkt bearbeiten' : 'Neues Produkt' }}
                </h1>
                <div class="flex items-center gap-2">
                    <a href="{{ route('syltjunkie.shop.products.index') }}" wire:navigate
                       class="px-3 py-1.5 text-[13px] text-gray-600 hover:text-gray-900 rounded-md border border-gray-300 hover:bg-gray-50 transition-colors">
                        Abbrechen
                    </a>
                    <button wire:click="save"
                            class="px-3 py-1.5 bg-blue-600 text-white text-[13px] font-medium rounded-md hover:bg-blue-700 transition-colors">
                        Speichern
                    </button>
                </div>
            </div>

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 text-[13px] px-3 py-2 rounded-md">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-3 gap-6">
                {{-- Main Column (2/3) --}}
                <div class="col-span-2 space-y-4">
                    {{-- Basic Info --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                        <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Grunddaten</h2>

                        <div>
                            <label class="block text-[12px] font-medium text-gray-600 mb-1">Name</label>
                            <input type="text" wire:model.blur="name"
                                   class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500" />
                            @error('name') <span class="text-red-500 text-[11px]">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[12px] font-medium text-gray-600 mb-1">Slug</label>
                            <input type="text" wire:model="slug"
                                   class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500 font-mono" />
                            @error('slug') <span class="text-red-500 text-[11px]">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[12px] font-medium text-gray-600 mb-1">Beschreibung (Markdown)</label>
                            <textarea wire:model.blur="description" rows="8"
                                      class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500 font-mono"></textarea>
                        </div>
                    </div>

                    {{-- ═══ Dimensionen & Varianten-Matrix (Physical only) ═══ --}}
                    @if($productType === 'physical' && $productId)
                        <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    @svg('heroicon-o-squares-2x2', 'w-4 h-4 text-gray-400')
                                    <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Dimensionen</h2>
                                </div>
                                <span class="text-[11px] text-gray-400">z.B. Größe, Farbe</span>
                            </div>

                            {{-- Existing Dimensions --}}
                            @foreach($dimensions as $dimension)
                                <div wire:key="dim-{{ $dimension->id }}" class="border border-gray-100 rounded-md p-3 bg-gray-50">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-[13px] font-medium text-gray-900">{{ $dimension->name }}</span>
                                        <button wire:click="removeDimension({{ $dimension->id }})"
                                                wire:confirm="Dimension und alle zugehörigen Werte löschen?"
                                                class="p-1 text-gray-400 hover:text-red-600">
                                            @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                        </button>
                                    </div>

                                    {{-- Values --}}
                                    <div class="flex flex-wrap gap-1.5 mb-2">
                                        @foreach($dimension->values as $value)
                                            <span wire:key="dv-{{ $value->id }}" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-blue-50 text-blue-700">
                                                {{ $value->value }}
                                                <button wire:click="removeDimensionValue({{ $value->id }})" class="hover:text-red-600">
                                                    @svg('heroicon-o-x-mark', 'w-3 h-3')
                                                </button>
                                            </span>
                                        @endforeach
                                    </div>

                                    {{-- Add Value --}}
                                    <div class="flex gap-1.5">
                                        <input type="text" wire:model="newDimensionValueInputs.{{ $dimension->id }}"
                                               wire:keydown.enter="addDimensionValue({{ $dimension->id }})"
                                               placeholder="Neuer Wert..."
                                               class="flex-1 rounded border-gray-300 text-[12px] px-2 py-1 focus:border-blue-500 focus:ring-blue-500" />
                                        <button wire:click="addDimensionValue({{ $dimension->id }})"
                                                class="px-2 py-1 text-[12px] font-medium rounded bg-blue-600 text-white hover:bg-blue-700 transition-colors">
                                            Hinzufügen
                                        </button>
                                    </div>
                                </div>
                            @endforeach

                            {{-- Add Dimension --}}
                            <div class="flex gap-2 pt-1">
                                <input type="text" wire:model="newDimensionName"
                                       wire:keydown.enter="addDimension"
                                       placeholder="Neue Dimension (z.B. Größe, Farbe)..."
                                       class="flex-1 rounded-md border-gray-300 text-[12px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500" />
                                <button wire:click="addDimension"
                                        class="px-3 py-1.5 text-[12px] font-medium rounded-md bg-gray-800 text-white hover:bg-gray-900 transition-colors">
                                    + Dimension
                                </button>
                            </div>
                        </div>

                        {{-- ═══ Varianten-Matrix (Artikel) ═══ --}}
                        @if($variants->isNotEmpty())
                            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        @svg('heroicon-o-table-cells', 'w-4 h-4 text-gray-400')
                                        <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Artikel-Matrix</h2>
                                        <span class="text-[11px] text-gray-400 ml-1">({{ $variants->count() }} Artikel)</span>
                                    </div>
                                    <button wire:click="saveVariantOverrides"
                                            class="px-2.5 py-1 text-[12px] font-medium rounded bg-blue-600 text-white hover:bg-blue-700 transition-colors">
                                        Artikel speichern
                                    </button>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                {{-- Dimension columns --}}
                                                @foreach($dimensions as $dimension)
                                                    @if($dimension->values->isNotEmpty())
                                                        <th class="px-3 py-2 text-left text-[10px] font-medium text-gray-500 uppercase tracking-wider">
                                                            {{ $dimension->name }}
                                                        </th>
                                                    @endif
                                                @endforeach
                                                <th class="px-3 py-2 text-left text-[10px] font-medium text-gray-500 uppercase tracking-wider">Artikelnr.</th>
                                                <th class="px-3 py-2 text-right text-[10px] font-medium text-gray-500 uppercase tracking-wider">Preis (Cent)</th>
                                                <th class="px-3 py-2 text-right text-[10px] font-medium text-gray-500 uppercase tracking-wider">Bestand</th>
                                                <th class="px-3 py-2 text-center text-[10px] font-medium text-gray-500 uppercase tracking-wider">Aktiv</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50">
                                            @foreach($variants as $variant)
                                                <tr wire:key="var-{{ $variant->id }}" class="hover:bg-gray-50">
                                                    {{-- Dimension values --}}
                                                    @foreach($dimensions as $dimension)
                                                        @if($dimension->values->isNotEmpty())
                                                            <td class="px-3 py-1.5">
                                                                @php
                                                                    $val = $variant->dimensionValues->first(fn($dv) => $dv->dimension_id === $dimension->id);
                                                                @endphp
                                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-700">
                                                                    {{ $val?->value ?? '—' }}
                                                                </span>
                                                            </td>
                                                        @endif
                                                    @endforeach

                                                    {{-- SKU / Artikelnummer --}}
                                                    <td class="px-3 py-1.5">
                                                        <input type="text"
                                                               wire:model.blur="variantOverrides.{{ $variant->id }}.sku"
                                                               class="w-full rounded border-gray-300 text-[12px] px-2 py-1 font-mono focus:border-blue-500 focus:ring-blue-500"
                                                               placeholder="SKU" />
                                                    </td>

                                                    {{-- Price override --}}
                                                    <td class="px-3 py-1.5 text-right">
                                                        <input type="number"
                                                               wire:model.blur="variantOverrides.{{ $variant->id }}.price_cents"
                                                               class="w-20 rounded border-gray-300 text-[12px] px-2 py-1 text-right focus:border-blue-500 focus:ring-blue-500"
                                                               placeholder="—" min="0" />
                                                    </td>

                                                    {{-- Stock --}}
                                                    <td class="px-3 py-1.5 text-right">
                                                        <input type="number"
                                                               wire:model.blur="variantOverrides.{{ $variant->id }}.stock_quantity"
                                                               class="w-16 rounded border-gray-300 text-[12px] px-2 py-1 text-right focus:border-blue-500 focus:ring-blue-500"
                                                               min="0" />
                                                    </td>

                                                    {{-- Active --}}
                                                    <td class="px-3 py-1.5 text-center">
                                                        <input type="checkbox"
                                                               wire:model.live="variantOverrides.{{ $variant->id }}.is_active"
                                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                    @elseif($productType === 'physical' && !$productId)
                        <div class="bg-white rounded-lg border border-gray-200 p-4">
                            <div class="text-center py-4">
                                <p class="text-[13px] text-gray-400">Speichere das Produkt zuerst, um Dimensionen und Varianten anzulegen.</p>
                            </div>
                        </div>
                    @endif

                    {{-- Digital File (Digital only) --}}
                    @if($productType === 'digital')
                        <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                            <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Download-Datei</h2>

                            @if($digitalFile)
                                <div class="flex items-center justify-between bg-gray-50 rounded-md p-3">
                                    <div class="flex items-center gap-2">
                                        @svg('heroicon-o-document', 'w-5 h-5 text-gray-400')
                                        <span class="text-[13px] text-gray-700">{{ $digitalFile->original_filename ?? 'Datei' }}</span>
                                    </div>
                                    <button wire:click="removeDigitalFile" class="text-red-400 hover:text-red-600">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </button>
                                </div>
                            @else
                                <div>
                                    <input type="file" wire:model="digitalFileUpload"
                                           class="text-[12px] text-gray-600" />
                                    @error('digitalFileUpload') <span class="text-red-500 text-[11px]">{{ $message }}</span> @enderror
                                </div>
                                @if($digitalFileUpload)
                                    <button wire:click="uploadDigitalFile"
                                            class="text-[12px] text-blue-600 hover:text-blue-800 font-medium">
                                        Hochladen
                                    </button>
                                @endif
                            @endif
                        </div>
                    @endif

                    {{-- Images --}}
                    @if($productId)
                        <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                            <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Bilder</h2>

                            {{-- Current Product Images --}}
                            @if($productImages->isNotEmpty())
                                <div class="grid grid-cols-6 gap-2">
                                    @foreach($productImages as $image)
                                        <div class="relative group">
                                            <img src="{{ $image->thumbnail_url }}" alt="{{ $image->title }}"
                                                 class="aspect-square object-cover rounded-md {{ $image->pivot->is_primary ? 'ring-2 ring-blue-500' : '' }}" />
                                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity rounded-md flex items-center justify-center gap-1">
                                                <button wire:click="setPrimaryImage({{ $image->id }})" class="p-1 bg-white rounded text-gray-700 hover:bg-blue-50" title="Als Hauptbild">
                                                    @svg('heroicon-o-star', 'w-3.5 h-3.5')
                                                </button>
                                                <button wire:click="detachImage({{ $image->id }})" class="p-1 bg-white rounded text-red-500 hover:bg-red-50" title="Entfernen">
                                                    @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                                </button>
                                            </div>
                                            @if($image->pivot->is_primary)
                                                <div class="absolute top-1 left-1">
                                                    @svg('heroicon-s-star', 'w-3.5 h-3.5 text-blue-500')
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Upload New --}}
                            <div class="border-t border-gray-100 pt-3">
                                <label class="block text-[12px] font-medium text-gray-600 mb-1">Neues Bild hochladen</label>
                                <div class="flex items-center gap-2">
                                    <input type="file" wire:model="imageUpload" accept="image/*" class="text-[12px] text-gray-600" />
                                    @if($imageUpload)
                                        <button wire:click="uploadImage" class="text-[12px] text-blue-600 hover:text-blue-800 font-medium">
                                            Hochladen
                                        </button>
                                    @endif
                                </div>
                                @error('imageUpload') <span class="text-red-500 text-[11px]">{{ $message }}</span> @enderror
                            </div>

                            {{-- Attach from Image Library --}}
                            <div class="border-t border-gray-100 pt-3">
                                <label class="block text-[12px] font-medium text-gray-600 mb-1">Aus Bilddatenbank</label>
                                <input type="text" wire:model.live.debounce.300ms="imageSearch" placeholder="Bilder suchen..."
                                       class="w-full rounded-md border-gray-300 text-[12px] px-2 py-1 focus:border-blue-500 focus:ring-blue-500 mb-2" />
                                <div class="grid grid-cols-8 gap-1.5 max-h-40 overflow-y-auto">
                                    @foreach($availableImages as $image)
                                        <button wire:click="attachImage({{ $image->id }})"
                                                class="aspect-square rounded overflow-hidden hover:ring-2 hover:ring-blue-400 transition-all">
                                            <img src="{{ $image->thumbnail_url }}" alt="{{ $image->title }}" class="w-full h-full object-cover" />
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Extra Fields --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                        <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Extra-Felder</h2>

                        @foreach($extraFields as $key => $value)
                            <div class="flex items-center gap-2">
                                <span class="text-[12px] font-medium text-gray-600 min-w-[100px]">{{ $key }}</span>
                                <span class="text-[12px] text-gray-800 flex-1">{{ $value }}</span>
                                <button wire:click="removeExtraField('{{ $key }}')" class="text-red-400 hover:text-red-600">
                                    @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                </button>
                            </div>
                        @endforeach

                        <div class="flex items-end gap-2">
                            <div>
                                <label class="block text-[11px] text-gray-500 mb-0.5">Key</label>
                                <input type="text" wire:model="newExtraKey" placeholder="z.B. material"
                                       class="rounded border-gray-300 text-[12px] px-2 py-1 focus:border-blue-500 focus:ring-blue-500" />
                            </div>
                            <div class="flex-1">
                                <label class="block text-[11px] text-gray-500 mb-0.5">Value</label>
                                <input type="text" wire:model="newExtraValue" placeholder="z.B. 100% Baumwolle"
                                       class="w-full rounded border-gray-300 text-[12px] px-2 py-1 focus:border-blue-500 focus:ring-blue-500" />
                            </div>
                            <button wire:click="addExtraField" class="px-2 py-1 text-[12px] text-blue-600 hover:text-blue-800 font-medium">
                                Hinzufügen
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Sidebar Column (1/3) --}}
                <div class="space-y-4">
                    {{-- Status & Type --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                        <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Einstellungen</h2>

                        <div>
                            <label class="block text-[12px] font-medium text-gray-600 mb-1">Status</label>
                            <select wire:model="status" class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                                <option value="draft">Entwurf</option>
                                <option value="active">Aktiv</option>
                                <option value="archived">Archiviert</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[12px] font-medium text-gray-600 mb-1">Produkttyp</label>
                            <select wire:model.live="productType" class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                                <option value="physical">Physisch</option>
                                <option value="digital">Digital</option>
                            </select>
                        </div>

                        <label class="inline-flex items-center gap-1.5">
                            <input type="checkbox" wire:model="isFeatured"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                            <span class="text-[13px] text-gray-700">Featured (Startseite)</span>
                        </label>
                    </div>

                    {{-- Pricing --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                        <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Preis</h2>

                        <div>
                            <label class="block text-[12px] font-medium text-gray-600 mb-1">Basispreis (Cent)</label>
                            <input type="number" wire:model="priceCents" min="0"
                                   class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500" />
                            @if($priceCents > 0)
                                <span class="text-[11px] text-gray-400 mt-0.5">= {{ number_format($priceCents / 100, 2, ',', '.') }} €</span>
                            @endif
                        </div>

                        <div>
                            <label class="block text-[12px] font-medium text-gray-600 mb-1">Streichpreis (Cent)</label>
                            <input type="number" wire:model="compareAtPriceCents" min="0"
                                   class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500" />
                        </div>
                    </div>

                    {{-- Stock (Physical without dimensions) --}}
                    @if($productType === 'physical')
                        <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                            <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Bestand</h2>
                            @if($variants->isNotEmpty())
                                <p class="text-[12px] text-gray-400">Bestand wird pro Artikel in der Matrix verwaltet.</p>
                            @else
                                <div>
                                    <label class="block text-[12px] font-medium text-gray-600 mb-1">Lagerbestand (leer = unbegrenzt)</label>
                                    <input type="number" wire:model="stockQuantity" min="0"
                                           class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500" />
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- SKU Prefix --}}
                    @if($productType === 'physical' && $productId)
                        <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                            <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Artikelnummer</h2>
                            <div>
                                <label class="block text-[12px] font-medium text-gray-600 mb-1">SKU-Prefix (für neue Varianten)</label>
                                <input type="text" wire:model="skuPrefix" placeholder="z.B. TSHIRT"
                                       class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 font-mono focus:border-blue-500 focus:ring-blue-500" />
                                <p class="text-[11px] text-gray-400 mt-0.5">Neue Varianten erhalten PREFIX-001, PREFIX-002, ...</p>
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
