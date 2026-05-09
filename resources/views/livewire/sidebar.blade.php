<div>
    {{-- Modul Header --}}
    <div x-show="!collapsed" class="p-3 text-sm italic text-[var(--ui-secondary)] uppercase border-b border-[var(--ui-border)] mb-2">
        Syltjunkie
    </div>

    {{-- Entity Graph --}}
    <x-ui-sidebar-list label="Entity Graph">
        <x-ui-sidebar-item :href="route('syltjunkie.dashboard')">
            @svg('heroicon-o-globe-alt', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Dashboard</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('syltjunkie.entities.index')">
            @svg('heroicon-o-building-storefront', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Entities</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('syltjunkie.entity-types.index')">
            @svg('heroicon-o-tag', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Entity Types</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- SEO Monitoring --}}
    <x-ui-sidebar-list label="SEO Monitoring">
        <x-ui-sidebar-item :href="route('syltjunkie.dashboard')">
            @svg('heroicon-o-signal', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Trend Signals</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('syltjunkie.entities.index')">
            @svg('heroicon-o-chart-bar', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Rankings</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('syltjunkie.entities.index')">
            @svg('heroicon-o-document-magnifying-glass', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Page Changes</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Google Business --}}
    <x-ui-sidebar-list label="Google Business">
        <x-ui-sidebar-item :href="route('syltjunkie.entities.index')">
            @svg('heroicon-o-map-pin', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Bewertungen</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('syltjunkie.entities.index')">
            @svg('heroicon-o-map', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Karte</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Collapsed: Icons-only --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--ui-border)]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('syltjunkie.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Dashboard">
                @svg('heroicon-o-globe-alt', 'w-5 h-5')
            </a>
            <a href="{{ route('syltjunkie.entities.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Entities">
                @svg('heroicon-o-building-storefront', 'w-5 h-5')
            </a>
            <a href="{{ route('syltjunkie.entity-types.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Entity Types">
                @svg('heroicon-o-tag', 'w-5 h-5')
            </a>
            <a href="{{ route('syltjunkie.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Trend Signals">
                @svg('heroicon-o-signal', 'w-5 h-5')
            </a>
            <a href="{{ route('syltjunkie.entities.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Bewertungen">
                @svg('heroicon-o-map-pin', 'w-5 h-5')
            </a>
        </div>
    </div>
</div>
