<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Entities'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Entities</h1>
            </div>

            {{-- Filters --}}
            <div class="flex items-center gap-3">
                <div class="flex-1">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Suche nach Name, Ort, Slug..."
                        class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                    />
                </div>
                <select wire:model.live="filterGroupId" class="rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                    <option value="">Alle Gruppen</option>
                    @foreach($groups as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="filterStatus" class="rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                    <option value="">Alle Status</option>
                    <option value="aktiv">Aktiv</option>
                    <option value="saisonal_geschlossen">Saisonal geschlossen</option>
                    <option value="dauerhaft_geschlossen">Dauerhaft geschlossen</option>
                </select>
            </div>

            {{-- Entity List --}}
            <div class="bg-white rounded-lg border border-gray-200">
                <div class="divide-y divide-gray-100">
                    @forelse($entities as $entity)
                    <a href="{{ route('syltjunkie.entity.detail', $entity) }}" class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-md bg-blue-50 flex items-center justify-center">
                                @svg('heroicon-o-building-storefront', 'w-4 h-4 text-blue-600')
                            </div>
                            <div>
                                <div class="text-[13px] font-medium text-gray-900">{{ $entity->name }}</div>
                                <div class="text-[11px] text-gray-400">
                                    {{ $entity->entityType?->group?->name }} &rarr; {{ $entity->entityType?->name }}
                                    @if($entity->ort) &middot; {{ $entity->ort }} @endif
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium
                                {{ $entity->status === 'aktiv' ? 'bg-green-50 text-green-700' : ($entity->status === 'saisonal_geschlossen' ? 'bg-yellow-50 text-yellow-700' : 'bg-red-50 text-red-700') }}">
                                {{ str_replace('_', ' ', ucfirst($entity->status)) }}
                            </span>
                        </div>
                    </a>
                    @empty
                    <div class="px-4 py-8 text-center text-[13px] text-gray-400">
                        Keine Entities gefunden.
                    </div>
                    @endforelse
                </div>
            </div>

            {{ $entities->links() }}
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
