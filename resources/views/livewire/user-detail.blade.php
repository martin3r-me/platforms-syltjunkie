<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Users', 'href' => route('syltjunkie.users.index')],
            ['label' => $user->email],
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
                    {{-- User Header --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h1 class="text-xl font-semibold text-gray-900">{{ $user->name ?? $user->email }}</h1>
                                @if($user->name)
                                    <p class="text-[12px] text-gray-400 mt-0.5">{{ $user->email }}</p>
                                @endif
                            </div>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-[12px] font-medium
                                {{ $user->status === 'active' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                                {{ $user->status === 'active' ? 'Aktiv' : 'Blockiert' }}
                            </span>
                        </div>

                        {{-- Status Actions --}}
                        <div class="flex items-center gap-2 border-t border-gray-100 pt-3">
                            @if($user->status === 'active')
                                <button wire:click="block"
                                        wire:confirm="Nutzer blockieren?"
                                        class="px-3 py-1.5 text-[12px] font-medium rounded-md border border-red-300 text-red-600 hover:bg-red-50 transition-colors">
                                    Blockieren
                                </button>
                            @else
                                <button wire:click="activate"
                                        class="px-3 py-1.5 text-[12px] font-medium rounded-md bg-green-600 text-white hover:bg-green-700 transition-colors">
                                    Aktivieren
                                </button>
                            @endif
                            <button wire:click="clearToken"
                                    wire:confirm="Token zurücksetzen? Der Nutzer wird ausgeloggt."
                                    class="px-3 py-1.5 text-[12px] font-medium rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors">
                                Token zurücksetzen
                            </button>
                        </div>
                    </div>

                    {{-- Gamification Card --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                        <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Gamification</h2>

                        <div class="flex items-center gap-6">
                            @php
                                $levelColors = [
                                    'tagesgast' => 'bg-gray-100 text-gray-600',
                                    'urlauber' => 'bg-blue-50 text-blue-700',
                                    'stammgast' => 'bg-indigo-50 text-indigo-700',
                                    'insulaner' => 'bg-purple-50 text-purple-700',
                                    'syltjunkie' => 'bg-amber-50 text-amber-700',
                                ];
                            @endphp
                            <div>
                                <div class="text-[11px] text-gray-400 uppercase tracking-wider">Level</div>
                                <span class="inline-flex items-center px-2.5 py-1 rounded text-[13px] font-semibold mt-1 {{ $levelColors[$user->current_level] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ $currentLevel['name'] }}
                                </span>
                            </div>
                            <div>
                                <div class="text-[11px] text-gray-400 uppercase tracking-wider">Punkte</div>
                                <div class="text-[20px] font-bold text-gray-900 font-mono mt-0.5">
                                    {{ number_format($user->points_balance, 0, ',', '.') }}
                                </div>
                            </div>
                        </div>

                        {{-- Progress to next level --}}
                        @if($nextLevel)
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between text-[11px] text-gray-500">
                                    <span>{{ $currentLevel['name'] }}</span>
                                    <span>{{ $nextLevel['name'] }} ({{ number_format($nextLevel['min_points'], 0, ',', '.') }} Punkte)</span>
                                </div>
                                <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-blue-500 rounded-full transition-all" style="width: {{ $progressPercent }}%"></div>
                                </div>
                                <div class="text-[11px] text-gray-400">
                                    Noch {{ number_format($nextLevel['min_points'] - $user->points_balance, 0, ',', '.') }} Punkte bis zum nächsten Level
                                </div>
                            </div>
                        @else
                            <div class="text-[12px] text-amber-600 font-medium">Höchstes Level erreicht!</div>
                        @endif

                        {{-- Level Overview --}}
                        <div class="border-t border-gray-100 pt-3">
                            <div class="flex items-center gap-1">
                                @foreach($levels as $level)
                                    @php
                                        $isReached = $user->points_balance >= $level['min_points'];
                                        $isCurrent = $level['key'] === $user->current_level;
                                    @endphp
                                    <div class="flex-1 text-center">
                                        <div class="h-1.5 rounded-full {{ $isReached ? 'bg-blue-500' : 'bg-gray-200' }} {{ $isCurrent ? 'ring-2 ring-blue-300' : '' }}"></div>
                                        <div class="text-[9px] mt-1 {{ $isCurrent ? 'text-blue-700 font-semibold' : ($isReached ? 'text-gray-600' : 'text-gray-300') }}">
                                            {{ $level['name'] }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Manual Points Award --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                        <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Punkte buchen</h2>
                        <div class="flex items-end gap-3">
                            <div class="flex-1">
                                <label class="block text-[11px] text-gray-500 mb-1">Aktion</label>
                                <input type="text" wire:model="awardAction"
                                       class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500" />
                            </div>
                            <div class="w-28">
                                <label class="block text-[11px] text-gray-500 mb-1">Punkte</label>
                                <input type="number" wire:model="awardPoints"
                                       class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500" />
                            </div>
                            <div class="flex-1">
                                <label class="block text-[11px] text-gray-500 mb-1">Notiz (optional)</label>
                                <input type="text" wire:model="awardNote"
                                       class="w-full rounded-md border-gray-300 text-[13px] px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500" />
                            </div>
                            <button wire:click="awardManualPoints"
                                    class="px-3 py-1.5 text-[12px] font-medium rounded-md bg-blue-600 text-white hover:bg-blue-700 transition-colors whitespace-nowrap">
                                Buchen
                            </button>
                        </div>
                        <p class="text-[11px] text-gray-400">Negative Werte für Korrekturbuchungen möglich.</p>
                    </div>

                    {{-- Points History --}}
                    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                            <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Punkte-Verlauf</h2>
                            <button wire:click="recalculateBalance"
                                    wire:confirm="Balance aus Ledger neu berechnen?"
                                    class="text-[11px] text-gray-400 hover:text-gray-600 font-medium">
                                Neu berechnen
                            </button>
                        </div>
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Datum</th>
                                    <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Aktion</th>
                                    <th class="px-4 py-2 text-right text-[11px] font-medium text-gray-500 uppercase tracking-wider">Punkte</th>
                                    <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @forelse($pointsHistory as $entry)
                                    <tr>
                                        <td class="px-4 py-2.5 text-[12px] text-gray-500">
                                            {{ $entry->created_at->format('d.m.Y H:i') }}
                                        </td>
                                        <td class="px-4 py-2.5 text-[13px] text-gray-900 font-medium font-mono">
                                            {{ $entry->action }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-[13px] font-mono font-medium {{ $entry->points >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $entry->points >= 0 ? '+' : '' }}{{ $entry->points }}
                                        </td>
                                        <td class="px-4 py-2.5 text-[12px] text-gray-400">
                                            @if($entry->meta)
                                                @foreach($entry->meta as $key => $value)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 text-[10px] mr-1">
                                                        {{ $key }}: {{ is_string($value) ? $value : json_encode($value) }}
                                                    </span>
                                                @endforeach
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-[13px] text-gray-400">
                                            Noch keine Punkte-Buchungen.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $pointsHistory->links() }}
                </div>

                {{-- Sidebar Column (1/3) --}}
                <div class="space-y-4">
                    {{-- User Info --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                        <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Nutzer-Info</h2>
                        <div class="space-y-1.5 text-[12px]">
                            <div class="flex justify-between">
                                <span class="text-gray-500">E-Mail</span>
                                <span class="text-gray-900 font-medium">{{ $user->email }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Name</span>
                                <span class="text-gray-900">{{ $user->name ?? '—' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Status</span>
                                <span class="font-medium {{ $user->status === 'active' ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $user->status === 'active' ? 'Aktiv' : 'Blockiert' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Timestamps --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                        <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Zeitverlauf</h2>
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-gray-400"></div>
                                <span class="text-[12px] text-gray-600">Registriert: {{ $user->created_at->format('d.m.Y H:i') }}</span>
                            </div>
                            @if($user->last_login_at)
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                    <span class="text-[12px] text-gray-600">Letzter Login: {{ $user->last_login_at->format('d.m.Y H:i') }}</span>
                                </div>
                            @endif
                            @if($user->token_expires_at)
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full {{ $user->token_expires_at->isFuture() ? 'bg-green-500' : 'bg-red-400' }}"></div>
                                    <span class="text-[12px] text-gray-600">
                                        Token {{ $user->token_expires_at->isFuture() ? 'gültig bis' : 'abgelaufen' }}: {{ $user->token_expires_at->format('d.m.Y H:i') }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Stats --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                        <h2 class="text-[13px] font-semibold text-gray-700 uppercase tracking-wider">Statistik</h2>
                        <div class="space-y-1.5 text-[12px]">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Buchungen gesamt</span>
                                <span class="text-gray-900 font-medium font-mono">{{ $user->pointsHistory()->count() }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Punkte verdient</span>
                                <span class="text-green-600 font-medium font-mono">+{{ number_format($user->pointsHistory()->where('points', '>', 0)->sum('points'), 0, ',', '.') }}</span>
                            </div>
                            @php $deducted = $user->pointsHistory()->where('points', '<', 0)->sum('points'); @endphp
                            @if($deducted < 0)
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Punkte abgezogen</span>
                                    <span class="text-red-600 font-medium font-mono">{{ number_format($deducted, 0, ',', '.') }}</span>
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
