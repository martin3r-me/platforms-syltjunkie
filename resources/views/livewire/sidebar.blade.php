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
        <x-ui-sidebar-item :href="route('syltjunkie.pipeline.index')">
            @svg('heroicon-o-funnel', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Pipeline</span>
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

    {{-- Entity-Verwaltung --}}
    <x-ui-sidebar-list label="Entity-Verwaltung">
        <x-ui-sidebar-item :href="route('syltjunkie.owners.index')">
            @svg('heroicon-o-key', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Inhaber</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- SEO Monitoring --}}
    <x-ui-sidebar-list label="SEO Monitoring">
        <x-ui-sidebar-item :href="route('syltjunkie.trend-signals.index')">
            @svg('heroicon-o-signal', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Trend Signals</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('syltjunkie.keywords.index')">
            @svg('heroicon-o-magnifying-glass', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Keywords</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('syltjunkie.rankings.index')">
            @svg('heroicon-o-chart-bar', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Rankings</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('syltjunkie.page-changes.index')">
            @svg('heroicon-o-document-magnifying-glass', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Page Changes</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Google Business --}}
    <x-ui-sidebar-list label="Google Business">
        <x-ui-sidebar-item :href="route('syltjunkie.reviews.index')">
            @svg('heroicon-o-map-pin', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Bewertungen</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('syltjunkie.map.index')">
            @svg('heroicon-o-map', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Karte</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('syltjunkie.weather.index')">
            @svg('heroicon-o-sun', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Wetter</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Media --}}
    <x-ui-sidebar-list label="Media">
        <x-ui-sidebar-item :href="route('syltjunkie.images.index')">
            @svg('heroicon-o-photo', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Bilddatenbank</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Content & Publishing --}}
    <x-ui-sidebar-list label="Content & Publishing">
        <x-ui-sidebar-item :href="route('syltjunkie.content.index')">
            @svg('heroicon-o-document-text', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Content</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('syltjunkie.channels.index')">
            @svg('heroicon-o-signal', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Channels</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('syltjunkie.posts.index')">
            @svg('heroicon-o-paper-airplane', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Posts</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Shop --}}
    <x-ui-sidebar-list label="Shop">
        <x-ui-sidebar-item :href="route('syltjunkie.shop.products.index')">
            @svg('heroicon-o-shopping-bag', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Produkte</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('syltjunkie.shop.orders.index')">
            @svg('heroicon-o-clipboard-document-list', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Bestellungen</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Collapsed: Icons-only --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--ui-border)]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('syltjunkie.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Dashboard">
                @svg('heroicon-o-globe-alt', 'w-5 h-5')
            </a>
            <a href="{{ route('syltjunkie.pipeline.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Pipeline">
                @svg('heroicon-o-funnel', 'w-5 h-5')
            </a>
            <a href="{{ route('syltjunkie.entities.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Entities">
                @svg('heroicon-o-building-storefront', 'w-5 h-5')
            </a>
            <a href="{{ route('syltjunkie.entity-types.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Entity Types">
                @svg('heroicon-o-tag', 'w-5 h-5')
            </a>
            <a href="{{ route('syltjunkie.owners.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Inhaber">
                @svg('heroicon-o-key', 'w-5 h-5')
            </a>
            <a href="{{ route('syltjunkie.trend-signals.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Trend Signals">
                @svg('heroicon-o-signal', 'w-5 h-5')
            </a>
            <a href="{{ route('syltjunkie.keywords.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Keywords">
                @svg('heroicon-o-magnifying-glass', 'w-5 h-5')
            </a>
            <a href="{{ route('syltjunkie.rankings.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Rankings">
                @svg('heroicon-o-chart-bar', 'w-5 h-5')
            </a>
            <a href="{{ route('syltjunkie.reviews.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Bewertungen">
                @svg('heroicon-o-map-pin', 'w-5 h-5')
            </a>
            <a href="{{ route('syltjunkie.map.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Karte">
                @svg('heroicon-o-map', 'w-5 h-5')
            </a>
            <a href="{{ route('syltjunkie.weather.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Wetter">
                @svg('heroicon-o-sun', 'w-5 h-5')
            </a>
            <a href="{{ route('syltjunkie.images.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Bilddatenbank">
                @svg('heroicon-o-photo', 'w-5 h-5')
            </a>
            <a href="{{ route('syltjunkie.content.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Content">
                @svg('heroicon-o-document-text', 'w-5 h-5')
            </a>
            <a href="{{ route('syltjunkie.channels.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Channels">
                @svg('heroicon-o-signal', 'w-5 h-5')
            </a>
            <a href="{{ route('syltjunkie.posts.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Posts">
                @svg('heroicon-o-paper-airplane', 'w-5 h-5')
            </a>
            <a href="{{ route('syltjunkie.shop.products.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Produkte">
                @svg('heroicon-o-shopping-bag', 'w-5 h-5')
            </a>
            <a href="{{ route('syltjunkie.shop.orders.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Bestellungen">
                @svg('heroicon-o-clipboard-document-list', 'w-5 h-5')
            </a>
        </div>
    </div>
</div>
