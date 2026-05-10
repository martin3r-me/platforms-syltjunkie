<x-ui-kanban-card :sortable-id="$card->id">
    <div class="space-y-2">
        <div class="font-medium text-sm text-[var(--ui-secondary)] truncate">{{ $card->name }}</div>

        @if($card->entityType)
            <div class="flex items-center gap-1">
                <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full border"
                      style="background-color: {{ $card->entityType->color ?? '#f3f4f6' }}20; border-color: {{ $card->entityType->color ?? '#d1d5db' }}40; color: {{ $card->entityType->color ?? '#6b7280' }}">
                    @if($card->entityType->icon)
                        @svg($card->entityType->icon, 'w-3 h-3')
                    @endif
                    {{ $card->entityType->name }}
                </span>
            </div>
        @endif

        @if($card->url)
            <div class="text-xs text-[var(--ui-muted)] truncate" title="{{ $card->url }}">
                @svg('heroicon-o-link', 'w-3 h-3 inline')
                {{ parse_url($card->url, PHP_URL_HOST) }}
            </div>
        @endif

        @if($card->latitude && $card->longitude)
            <div class="text-xs text-[var(--ui-muted)]">
                @svg('heroicon-o-map-pin', 'w-3 h-3 inline')
                {{ number_format($card->latitude, 4) }}, {{ number_format($card->longitude, 4) }}
            </div>
        @endif

        <div class="flex items-center gap-1 pt-1 border-t border-[var(--ui-border)]/40">
            <button
                wire:click="openCardEditor({{ $card->id }})"
                class="text-xs text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
            >
                @svg('heroicon-o-pencil-square', 'w-3.5 h-3.5 inline')
                Bearbeiten
            </button>

            @if($card->entity_type_id)
                <button
                    wire:click="convertToEntity({{ $card->id }})"
                    wire:confirm="Card in Entity konvertieren?"
                    class="text-xs text-indigo-600 hover:text-indigo-800 transition-colors ml-auto"
                >
                    @svg('heroicon-o-arrow-right-circle', 'w-3.5 h-3.5 inline')
                    Entity
                </button>
            @endif
        </div>
    </div>
</x-ui-kanban-card>
