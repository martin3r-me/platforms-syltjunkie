<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Pipeline" icon="heroicon-o-funnel" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Pipeline'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="createSlot">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Spalte</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    @if(session()->has('error'))
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded">
            {{ session('error') }}
        </div>
    @endif

    @if(session()->has('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded">
            {{ session('success') }}
        </div>
    @endif

    @if($slots->count() > 0)
        <div class="pipeline-board-container">
            <x-ui-kanban-container sortable="updateSlotOrder" sortable-group="updateCardOrder">
                @foreach($slots as $slot)
                    <x-ui-kanban-column :title="$slot->name" :sortable-id="$slot->id" :scrollable="true">
                        <x-slot name="headerActions">
                            <button
                                wire:click="createCard({{ $slot->id }})"
                                class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                                title="Neue Card"
                            >
                                @svg('heroicon-o-plus-circle', 'w-4 h-4')
                            </button>
                            <button
                                x-data
                                x-on:click="
                                    let name = prompt('Slot umbenennen:', '{{ $slot->name }}');
                                    if (name && name.trim()) $wire.renameSlot({{ $slot->id }}, name.trim());
                                "
                                class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                                title="Umbenennen"
                            >
                                @svg('heroicon-o-pencil', 'w-4 h-4')
                            </button>
                            <button
                                x-data
                                x-on:click="if (confirm('Slot löschen? Cards werden nicht gelöscht.')) $wire.deleteSlot({{ $slot->id }})"
                                class="text-[var(--ui-muted)] hover:text-red-500 transition-colors"
                                title="Löschen"
                            >
                                @svg('heroicon-o-trash', 'w-4 h-4')
                            </button>
                        </x-slot>

                        @foreach($slot->cards as $card)
                            @include('syltjunkie::livewire.partials.pipeline-card', ['card' => $card])
                        @endforeach
                    </x-ui-kanban-column>
                @endforeach
            </x-ui-kanban-container>
        </div>
    @else
        <div class="flex items-center justify-center h-full">
            <div class="bg-white rounded-xl border border-[var(--ui-border)]/60 shadow-sm p-12 text-center max-w-md">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-indigo-50 mb-4">
                    @svg('heroicon-o-funnel', 'w-8 h-8 text-indigo-600')
                </div>
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-2">Pipeline einrichten</h3>
                <p class="text-sm text-[var(--ui-muted)] mb-4">Erstelle Spalten, um Entities durch deinen Workflow zu leiten.</p>
                <x-ui-button variant="primary" size="sm" wire:click="createSlot">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Spalte erstellen</span>
                </x-ui-button>
            </div>
        </div>
    @endif

    {{-- Card Editor Modal --}}
    @if($editingCardId)
        @include('syltjunkie::livewire.partials.pipeline-card-editor')
    @endif
</x-ui-page>
