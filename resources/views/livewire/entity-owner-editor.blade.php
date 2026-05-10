<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Inhaber', 'href' => route('syltjunkie.owners.index')],
            ['label' => $owner->email],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="max-w-2xl space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Inhaber bearbeiten</h1>
                @if($owner->status === 'approved')
                    <button
                        wire:click="sendMagicLink"
                        wire:confirm="Magic Link an {{ $owner->email }} senden?"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[13px] font-medium text-blue-700 bg-blue-50 rounded-md hover:bg-blue-100 transition-colors"
                    >
                        @svg('heroicon-o-paper-airplane', 'w-4 h-4')
                        Magic Link senden
                    </button>
                @endif
            </div>

            @if(session('success'))
                <div class="p-3 text-[13px] text-green-700 bg-green-50 rounded-md border border-green-200">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="p-3 text-[13px] text-red-700 bg-red-50 rounded-md border border-red-200">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Info --}}
            <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Kontaktdaten</h2>
                <div class="grid grid-cols-2 gap-4 text-[13px]">
                    <div>
                        <span class="text-gray-500">E-Mail:</span>
                        <span class="ml-1 text-gray-900 font-medium">{{ $owner->email }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Name:</span>
                        <span class="ml-1 text-gray-900">{{ $owner->name ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Erstellt:</span>
                        <span class="ml-1 text-gray-900">{{ $owner->created_at->format('d.m.Y H:i') }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Letzte Anmeldung:</span>
                        <span class="ml-1 text-gray-900">{{ $owner->last_login_at?->format('d.m.Y H:i') ?? '—' }}</span>
                    </div>
                    @if($owner->approvedBy)
                        <div>
                            <span class="text-gray-500">Freigegeben von:</span>
                            <span class="ml-1 text-gray-900">{{ $owner->approvedBy->name }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Freigegeben am:</span>
                            <span class="ml-1 text-gray-900">{{ $owner->approved_at?->format('d.m.Y H:i') }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Form --}}
            <form wire:submit="save" class="space-y-4">
                {{-- Status --}}
                <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                    <label class="block text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Status</label>
                    <select wire:model="status" class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                        <option value="pending">Ausstehend</option>
                        <option value="approved">Freigegeben</option>
                        <option value="blocked">Blockiert</option>
                    </select>
                </div>

                {{-- Entity zuordnen --}}
                <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                    <label class="block text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Entity zuordnen</label>

                    @if($currentEntity)
                        <div class="flex items-center justify-between p-2 bg-blue-50 rounded-md">
                            <span class="text-[13px] text-blue-900 font-medium">{{ $currentEntity->name }}</span>
                            <button type="button" wire:click="$set('entityId', null)" class="text-[12px] text-blue-600 hover:text-blue-800">
                                Entfernen
                            </button>
                        </div>
                    @endif

                    <input
                        type="text"
                        wire:model.live.debounce.300ms="entitySearch"
                        placeholder="Entity suchen..."
                        class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                    />

                    @if($entities->isNotEmpty())
                        <div class="border border-gray-200 rounded-md divide-y divide-gray-100 max-h-48 overflow-y-auto">
                            @foreach($entities as $entity)
                                <button
                                    type="button"
                                    wire:click="$set('entityId', {{ $entity->id }}); $set('entitySearch', '')"
                                    class="w-full text-left px-3 py-2 text-[13px] hover:bg-gray-50 transition-colors"
                                >
                                    <span class="font-medium text-gray-900">{{ $entity->name }}</span>
                                    <span class="text-gray-400 ml-1">{{ $entity->slug }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Notizen --}}
                <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                    <label class="block text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Interne Notizen</label>
                    <textarea
                        wire:model="notes"
                        rows="3"
                        class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                        placeholder="Optionale Notizen..."
                    ></textarea>
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-3">
                    <button type="submit" class="px-4 py-2 text-[13px] font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors">
                        Speichern
                    </button>
                    <a href="{{ route('syltjunkie.owners.index') }}" wire:navigate class="px-4 py-2 text-[13px] font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors">
                        Abbrechen
                    </a>
                </div>
            </form>
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
