<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Syltjunkie</h1>
                <p class="text-[13px] text-gray-500 mt-1">Entity Graph &mdash; das digitale Tor zur Insel Sylt</p>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Entities</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $entityCount }}</div>
                    <div class="text-[11px] text-gray-400 mt-1">Erfasste Objekte</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Entity Types</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $typeCount }}</div>
                    <div class="text-[11px] text-gray-400 mt-1">Definierte Typen</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Gruppen</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $groupCount }}</div>
                    <div class="text-[11px] text-gray-400 mt-1">Type-Gruppen</div>
                </div>
            </div>

            {{-- Recent Entities --}}
            @if($recentEntities->count())
            <div class="bg-white rounded-lg border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h2 class="text-[13px] font-semibold text-gray-700">Zuletzt erfasst</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($recentEntities as $entity)
                    <a href="{{ route('syltjunkie.entity.detail', $entity) }}" class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-md bg-blue-50 flex items-center justify-center">
                                @svg('heroicon-o-building-storefront', 'w-4 h-4 text-blue-600')
                            </div>
                            <div>
                                <div class="text-[13px] font-medium text-gray-900">{{ $entity->name }}</div>
                                <div class="text-[11px] text-gray-400">{{ $entity->entityType?->name }} &middot; {{ $entity->ort }}</div>
                            </div>
                        </div>
                        <div class="text-[11px] text-gray-400">
                            {{ $entity->created_at->diffForHumans() }}
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
            @else
            <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                    @svg('heroicon-o-globe-alt', 'w-6 h-6 text-gray-400')
                </div>
                <h3 class="text-[13px] font-semibold text-gray-700 mb-1">Noch keine Entities</h3>
                <p class="text-[12px] text-gray-400">Starte mit dem Anlegen von Entity Types und f&uuml;lle den Graph.</p>
            </div>
            @endif
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
