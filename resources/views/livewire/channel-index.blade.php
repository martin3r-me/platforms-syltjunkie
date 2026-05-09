<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Channels'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Header --}}
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Channels</h1>
                    <p class="text-[13px] text-gray-500 mt-1">{{ $channels->count() }} Channels konfiguriert</p>
                </div>
                <button wire:click="openCreateModal"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-[13px] font-medium text-white hover:bg-blue-700">
                    Channel anlegen
                </button>
            </div>

            {{-- Channel List --}}
            <div class="space-y-3">
                @forelse($channels as $channel)
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0">
                                @if($channel->type === 'instagram')
                                    <svg class="w-5 h-5 text-pink-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                                @elseif($channel->type === 'facebook')
                                    <svg class="w-5 h-5 text-blue-700" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                @else
                                    @svg('heroicon-o-globe-alt', 'w-5 h-5 text-gray-400')
                                @endif
                            </div>
                            <div>
                                <div class="text-[14px] font-medium text-gray-900">{{ $channel->name }}</div>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600">
                                        {{ $channel->type }}
                                    </span>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium
                                        {{ $channel->status === 'active' ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700' }}">
                                        {{ $channel->status }}
                                    </span>
                                    <span class="text-[11px] text-gray-400">{{ $channel->posts_count }} Posts</span>
                                    @if($channel->posts->first()?->published_at)
                                        <span class="text-[11px] text-gray-400">
                                            Letzter: {{ $channel->posts->first()->published_at->format('d.m.Y') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button wire:click="toggleStatus({{ $channel->id }})" title="Status wechseln"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100">
                                @if($channel->status === 'active')
                                    @svg('heroicon-o-pause', 'w-4 h-4')
                                @else
                                    @svg('heroicon-o-play', 'w-4 h-4')
                                @endif
                            </button>
                            <button wire:click="openEditModal({{ $channel->id }})" title="Bearbeiten"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100">
                                @svg('heroicon-o-pencil', 'w-4 h-4')
                            </button>
                            <button wire:click="deleteChannel({{ $channel->id }})" wire:confirm="Channel wirklich löschen?"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50">
                                @svg('heroicon-o-trash', 'w-4 h-4')
                            </button>
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-12 text-[13px] text-gray-400">
                    Noch keine Channels angelegt.
                </div>
                @endforelse
            </div>
        </div>

        {{-- Modal --}}
        @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/30" wire:click.self="$set('showModal', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
                <h2 class="text-[15px] font-semibold text-gray-900 mb-4">
                    {{ $editingChannelId ? 'Channel bearbeiten' : 'Neuen Channel anlegen' }}
                </h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-[11px] text-gray-400 uppercase tracking-wider mb-1">Name</label>
                        <input type="text" wire:model="formName" placeholder="z.B. Syltjunkie Instagram"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" />
                    </div>

                    <div>
                        <label class="block text-[11px] text-gray-400 uppercase tracking-wider mb-1">Typ</label>
                        <select wire:model.live="formType"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <option value="instagram">Instagram</option>
                            <option value="facebook">Facebook</option>
                            <option value="website">Website</option>
                        </select>
                    </div>

                    @if($formType === 'instagram')
                    <div>
                        <label class="block text-[11px] text-gray-400 uppercase tracking-wider mb-1">Meta-Verbindung</label>
                        <select wire:model.live="formIntegrationConnectionId"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <option value="">-- Verbindung wählen --</option>
                            @foreach($integrationConnections as $conn)
                                <option value="{{ $conn->id }}">{{ $conn->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] text-gray-400 uppercase tracking-wider mb-1">Instagram Account</label>
                        <select wire:model="formInstagramAccountId"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <option value="">-- Account wählen --</option>
                            @foreach($instagramAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->username }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <div>
                        <label class="block text-[11px] text-gray-400 uppercase tracking-wider mb-1">Standard-Hashtags (komma-getrennt)</label>
                        <input type="text" wire:model="formDefaultHashtags" placeholder="#sylt, #nordsee"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 mt-6">
                    <button wire:click="$set('showModal', false)"
                        class="rounded-lg px-4 py-2 text-[13px] font-medium text-gray-600 hover:bg-gray-100">
                        Abbrechen
                    </button>
                    <button wire:click="saveChannel"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-[13px] font-medium text-white hover:bg-blue-700">
                        Speichern
                    </button>
                </div>
            </div>
        </div>
        @endif
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
