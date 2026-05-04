<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Entities', 'href' => route('syltjunkie.entities.index')],
            ['label' => $entity->name],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Header --}}
            <div>
                <h1 class="text-xl font-semibold text-gray-900">{{ $entity->name }}</h1>
                <p class="text-[13px] text-gray-500 mt-1">
                    {{ $entity->entityType?->group?->name }} &rarr; {{ $entity->entityType?->name }}
                    @if($entity->ort) &middot; {{ $entity->ort }} @endif
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Main Info --}}
                <div class="lg:col-span-2 space-y-6">
                    {{-- Base Fields --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <h2 class="text-[13px] font-semibold text-gray-700 mb-3">Stammdaten</h2>
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-[13px]">
                            <div>
                                <dt class="text-gray-400">Slug</dt>
                                <dd class="text-gray-900 font-mono">{{ $entity->slug }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400">Status</dt>
                                <dd>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium
                                        {{ $entity->status === 'aktiv' ? 'bg-green-50 text-green-700' : ($entity->status === 'saisonal_geschlossen' ? 'bg-yellow-50 text-yellow-700' : 'bg-red-50 text-red-700') }}">
                                        {{ str_replace('_', ' ', ucfirst($entity->status)) }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-400">Saison</dt>
                                <dd class="text-gray-900">{{ str_replace('_', ' ', ucfirst($entity->season)) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400">Quelle</dt>
                                <dd class="text-gray-900">{{ str_replace('_', ' ', ucfirst($entity->source)) }}</dd>
                            </div>
                            @if($entity->latitude && $entity->longitude)
                            <div>
                                <dt class="text-gray-400">Koordinaten</dt>
                                <dd class="text-gray-900 font-mono text-[12px]">{{ $entity->latitude }}, {{ $entity->longitude }}</dd>
                            </div>
                            @endif
                        </dl>
                        @if($entity->description)
                        <div class="mt-4 pt-3 border-t border-gray-100">
                            <p class="text-[13px] text-gray-600">{{ $entity->description }}</p>
                        </div>
                        @endif
                    </div>

                    {{-- Extra Fields --}}
                    @if($entity->extra_fields && count($entity->extra_fields))
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <h2 class="text-[13px] font-semibold text-gray-700 mb-3">Typ-spezifische Felder</h2>
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-[13px]">
                            @foreach($entity->extra_fields as $key => $value)
                            <div>
                                <dt class="text-gray-400">{{ str_replace('_', ' ', ucfirst($key)) }}</dt>
                                <dd class="text-gray-900">
                                    @if(is_array($value)) {{ implode(', ', $value) }}
                                    @elseif(is_bool($value)) {{ $value ? 'Ja' : 'Nein' }}
                                    @else {{ $value }}
                                    @endif
                                </dd>
                            </div>
                            @endforeach
                        </dl>
                    </div>
                    @endif
                </div>

                {{-- Relationships Sidebar --}}
                <div class="space-y-6">
                    {{-- Outgoing --}}
                    @if($entity->outgoingRelationships->count())
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <h2 class="text-[13px] font-semibold text-gray-700 mb-3">Beziehungen (ausgehend)</h2>
                        <div class="space-y-2">
                            @foreach($entity->outgoingRelationships as $rel)
                            <a href="{{ route('syltjunkie.entity.detail', $rel->targetEntity) }}" class="flex items-center gap-2 text-[13px] hover:bg-gray-50 rounded p-1.5 -mx-1.5">
                                <span class="text-gray-400">{{ $rel->relationType?->name }}</span>
                                <span class="text-gray-900 font-medium">{{ $rel->targetEntity?->name }}</span>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Incoming --}}
                    @if($entity->incomingRelationships->count())
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <h2 class="text-[13px] font-semibold text-gray-700 mb-3">Beziehungen (eingehend)</h2>
                        <div class="space-y-2">
                            @foreach($entity->incomingRelationships as $rel)
                            <a href="{{ route('syltjunkie.entity.detail', $rel->sourceEntity) }}" class="flex items-center gap-2 text-[13px] hover:bg-gray-50 rounded p-1.5 -mx-1.5">
                                <span class="text-gray-400">{{ $rel->relationType?->inverse_name ?? $rel->relationType?->name }}</span>
                                <span class="text-gray-900 font-medium">{{ $rel->sourceEntity?->name }}</span>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
