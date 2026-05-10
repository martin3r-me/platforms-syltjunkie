<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/30" wire:keydown.escape="$set('editingCardId', null)">
    <div class="bg-white rounded-xl shadow-xl border border-[var(--ui-border)] w-full max-w-lg mx-4" @click.away="$wire.set('editingCardId', null)">
        <div class="flex items-center justify-between px-6 py-4 border-b border-[var(--ui-border)]">
            <h3 class="text-base font-semibold text-[var(--ui-secondary)]">Card bearbeiten</h3>
            <button wire:click="$set('editingCardId', null)" class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]">
                @svg('heroicon-o-x-mark', 'w-5 h-5')
            </button>
        </div>

        <div class="px-6 py-4 space-y-4">
            {{-- Name --}}
            <div>
                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Name</label>
                <input type="text" wire:model="cardForm.name" class="w-full rounded-lg border border-[var(--ui-border)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" />
                @error('cardForm.name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>

            {{-- URL --}}
            <div>
                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">URL</label>
                <input type="url" wire:model="cardForm.url" placeholder="https://..." class="w-full rounded-lg border border-[var(--ui-border)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" />
                @error('cardForm.url') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>

            {{-- Entity Type --}}
            <div>
                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Entity-Typ</label>
                <select wire:model="cardForm.entity_type_id" class="w-full rounded-lg border border-[var(--ui-border)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    <option value="">— Kein Typ —</option>
                    @foreach($entityTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
                @error('cardForm.entity_type_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>

            {{-- Coordinates --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Latitude</label>
                    <input type="number" step="0.0000001" wire:model="cardForm.latitude" placeholder="54.9079" class="w-full rounded-lg border border-[var(--ui-border)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" />
                    @error('cardForm.latitude') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Longitude</label>
                    <input type="number" step="0.0000001" wire:model="cardForm.longitude" placeholder="8.3047" class="w-full rounded-lg border border-[var(--ui-border)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500" />
                    @error('cardForm.longitude') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Notes --}}
            <div>
                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Notizen</label>
                <textarea wire:model="cardForm.notes" rows="3" class="w-full rounded-lg border border-[var(--ui-border)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"></textarea>
                @error('cardForm.notes') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-[var(--ui-border)]">
            <x-ui-button variant="ghost" size="sm" wire:click="$set('editingCardId', null)">Abbrechen</x-ui-button>
            <x-ui-button variant="primary" size="sm" wire:click="saveCard">Speichern</x-ui-button>
        </div>
    </div>
</div>
