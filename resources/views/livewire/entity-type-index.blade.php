<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Entity Types'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            <h1 class="text-xl font-semibold text-gray-900">Entity Types</h1>

            @forelse($groups as $group)
            <div class="bg-white rounded-lg border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h2 class="text-[13px] font-semibold text-gray-700">{{ $group->name }}</h2>
                    @if($group->description)
                        <p class="text-[11px] text-gray-400 mt-0.5">{{ $group->description }}</p>
                    @endif
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($group->entityTypes as $type)
                    <div class="flex items-center justify-between px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-md bg-gray-50 flex items-center justify-center">
                                @svg('heroicon-o-tag', 'w-4 h-4 text-gray-400')
                            </div>
                            <div>
                                <div class="text-[13px] font-medium text-gray-900">{{ $type->name }}</div>
                                <div class="text-[11px] text-gray-400">{{ $type->code }}</div>
                            </div>
                        </div>
                        <div class="text-[13px] text-gray-500 tabular-nums">
                            {{ $type->entities_count }} Entities
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @empty
            <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                <p class="text-[13px] text-gray-400">Noch keine Entity Type Groups definiert.</p>
            </div>
            @endforelse
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
