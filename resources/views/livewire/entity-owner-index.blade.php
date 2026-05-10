<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Inhaber'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Entity-Inhaber</h1>
            </div>

            {{-- Filters --}}
            <div class="flex items-center gap-3">
                <div class="flex-1">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Suche nach E-Mail oder Name..."
                        class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                    />
                </div>
                <select wire:model.live="filterStatus" class="rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                    <option value="">Alle Status</option>
                    <option value="pending">Ausstehend</option>
                    <option value="approved">Freigegeben</option>
                    <option value="blocked">Blockiert</option>
                </select>
            </div>

            {{-- Table --}}
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">E-Mail</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Entity</th>
                                <th class="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Letzte Anmeldung</th>
                                <th class="px-4 py-2.5 text-right text-[11px] font-medium text-gray-500 uppercase tracking-wider">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($owners as $owner)
                                <tr class="hover:bg-gray-50 transition-colors {{ $owner->status === 'pending' ? 'bg-yellow-50/50' : '' }}">
                                    <td class="px-4 py-2.5 text-[13px] text-gray-900 font-medium">{{ $owner->email }}</td>
                                    <td class="px-4 py-2.5 text-[13px] text-gray-600">{{ $owner->name ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-[13px] text-gray-600">{{ $owner->entity?->name ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium
                                            {{ $owner->status === 'approved' ? 'bg-green-50 text-green-700' : ($owner->status === 'pending' ? 'bg-yellow-50 text-yellow-700' : 'bg-red-50 text-red-700') }}">
                                            {{ $owner->status === 'approved' ? 'Freigegeben' : ($owner->status === 'pending' ? 'Ausstehend' : 'Blockiert') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-[13px] text-gray-500">
                                        {{ $owner->last_login_at?->format('d.m.Y H:i') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            @if($owner->status === 'pending')
                                                <button
                                                    wire:click="approve({{ $owner->id }})"
                                                    wire:confirm="Inhaber freigeben?"
                                                    class="text-[12px] text-green-600 hover:text-green-800 font-medium"
                                                >
                                                    Freigeben
                                                </button>
                                                <button
                                                    wire:click="block({{ $owner->id }})"
                                                    wire:confirm="Inhaber blockieren?"
                                                    class="text-[12px] text-red-600 hover:text-red-800 font-medium"
                                                >
                                                    Blockieren
                                                </button>
                                            @endif
                                            <a
                                                href="{{ route('syltjunkie.owners.edit', $owner) }}"
                                                wire:navigate
                                                class="text-[12px] text-blue-600 hover:text-blue-800 font-medium"
                                            >
                                                Bearbeiten
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-[13px] text-gray-400">
                                        Keine Inhaber-Anfragen vorhanden.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $owners->links() }}
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
