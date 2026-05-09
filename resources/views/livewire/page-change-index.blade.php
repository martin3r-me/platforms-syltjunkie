<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Page Changes'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Page Changes</h1>
            </div>

            {{-- Filters --}}
            <div class="flex items-center gap-3">
                <select wire:model.live="filterSeverity" class="rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                    <option value="">Alle Severity</option>
                    <option value="critical">Critical</option>
                    <option value="major">Major</option>
                    <option value="minor">Minor</option>
                </select>
                <select wire:model.live="filterType" class="rounded-md border-gray-300 text-[13px] px-3 py-1.5">
                    <option value="">Alle Typen</option>
                    <option value="title_changed">Title</option>
                    <option value="description_changed">Description</option>
                    <option value="h1_changed">H1</option>
                    <option value="status_changed">HTTP Status</option>
                    <option value="content_changed">Content</option>
                </select>
            </div>

            {{-- Changes List --}}
            @if($changes->count())
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Severity</th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Entity / URL</th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Typ</th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Vorher</th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Nachher</th>
                            <th class="px-4 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">Erkannt</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($changes as $change)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <span class="inline-flex px-1.5 py-0.5 rounded text-[11px] font-medium
                                    {{ ($change->severity ?? 'minor') === 'critical' ? 'bg-red-50 text-red-600' : (($change->severity ?? 'minor') === 'major' ? 'bg-yellow-50 text-yellow-600' : 'bg-gray-100 text-gray-600') }}">
                                    {{ $change->severity ?? 'minor' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if($change->entityUrl?->entity)
                                    <div class="text-[13px] font-medium">
                                        <a href="{{ route('syltjunkie.entity.detail', $change->entityUrl->entity) }}" class="text-blue-600 hover:text-blue-800">{{ $change->entityUrl->entity->name }}</a>
                                    </div>
                                @endif
                                <div class="text-[11px] text-gray-400 truncate max-w-[250px]">{{ $change->entityUrl?->url }}</div>
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-500">
                                {{ str_replace('_', ' ', $change->change_type) }}
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-500 max-w-[200px]">
                                <div class="truncate">{{ $change->old_value ?: '&mdash;' }}</div>
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-900 max-w-[200px]">
                                <div class="truncate">{{ $change->new_value ?: '&mdash;' }}</div>
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-500">
                                {{ $change->detected_at?->format('d.m.Y') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $changes->links() }}
            </div>
            @else
            <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                    @svg('heroicon-o-document-magnifying-glass', 'w-6 h-6 text-gray-400')
                </div>
                <h3 class="text-[13px] font-semibold text-gray-700 mb-1">Keine Page Changes</h3>
                <p class="text-[12px] text-gray-400">Nutze den Snapshot-Command, um Seiten&auml;nderungen zu erkennen.</p>
            </div>
            @endif
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
