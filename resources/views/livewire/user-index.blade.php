<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Users'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">App-Nutzer</h1>
                <span class="text-[12px] text-gray-400">{{ $users->total() }} Nutzer</span>
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
                    <option value="active">Aktiv</option>
                    <option value="blocked">Blockiert</option>
                </select>
                <select wire:model.live="filterLevel" class="rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                    <option value="">Alle Level</option>
                    @foreach($levels as $level)
                        <option value="{{ $level['key'] }}">{{ $level['name'] }}</option>
                    @endforeach
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
                                <th class="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-2.5 text-center text-[11px] font-medium text-gray-500 uppercase tracking-wider">Level</th>
                                <th class="px-4 py-2.5 text-right text-[11px] font-medium text-gray-500 uppercase tracking-wider">Punkte</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Letzter Login</th>
                                <th class="px-4 py-2.5 text-right text-[11px] font-medium text-gray-500 uppercase tracking-wider">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($users as $user)
                                <tr class="hover:bg-gray-50 transition-colors {{ $user->status === 'blocked' ? 'bg-red-50/30' : '' }}">
                                    <td class="px-4 py-2.5 text-[13px] text-gray-900 font-medium">
                                        <a href="{{ route('syltjunkie.users.detail', $user) }}" wire:navigate class="hover:text-blue-600">
                                            {{ $user->email }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2.5 text-[13px] text-gray-600">{{ $user->name ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium
                                            {{ $user->status === 'active' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                                            {{ $user->status === 'active' ? 'Aktiv' : 'Blockiert' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        @php
                                            $levelColors = [
                                                'tagesgast' => 'bg-gray-100 text-gray-600',
                                                'urlauber' => 'bg-blue-50 text-blue-700',
                                                'stammgast' => 'bg-indigo-50 text-indigo-700',
                                                'insulaner' => 'bg-purple-50 text-purple-700',
                                                'syltjunkie' => 'bg-amber-50 text-amber-700',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium {{ $levelColors[$user->current_level] ?? 'bg-gray-100 text-gray-600' }}">
                                            {{ collect($levels)->firstWhere('key', $user->current_level)['name'] ?? $user->current_level }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-[13px] text-gray-600 font-mono">
                                        {{ number_format($user->points_balance, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-2.5 text-[13px] text-gray-500">
                                        {{ $user->last_login_at?->format('d.m.Y H:i') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            @if($user->status === 'active')
                                                <button
                                                    wire:click="block({{ $user->id }})"
                                                    wire:confirm="Nutzer blockieren?"
                                                    class="text-[12px] text-red-600 hover:text-red-800 font-medium"
                                                >
                                                    Blockieren
                                                </button>
                                            @else
                                                <button
                                                    wire:click="activate({{ $user->id }})"
                                                    wire:confirm="Nutzer aktivieren?"
                                                    class="text-[12px] text-green-600 hover:text-green-800 font-medium"
                                                >
                                                    Aktivieren
                                                </button>
                                            @endif
                                            <a
                                                href="{{ route('syltjunkie.users.detail', $user) }}"
                                                wire:navigate
                                                class="text-[12px] text-blue-600 hover:text-blue-800 font-medium"
                                            >
                                                Details
                                            </a>
                                            <button
                                                wire:click="delete({{ $user->id }})"
                                                wire:confirm="Nutzer endgültig löschen? Alle Punkte gehen verloren."
                                                class="text-[12px] text-red-600 hover:text-red-800 font-medium"
                                            >
                                                Löschen
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-[13px] text-gray-400">
                                        Keine Nutzer vorhanden.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $users->links() }}
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
