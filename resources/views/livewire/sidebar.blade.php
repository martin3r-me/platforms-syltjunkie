<x-ui-page-sidebar>
    <x-ui-page-sidebar-group label="Entity Graph">
        <x-ui-page-sidebar-item
            :href="route('syltjunkie.dashboard')"
            icon="heroicon-o-globe-alt"
            label="Dashboard"
            :active="request()->routeIs('syltjunkie.dashboard')"
        />
        <x-ui-page-sidebar-item
            :href="route('syltjunkie.entities.index')"
            icon="heroicon-o-building-storefront"
            label="Entities"
            :active="request()->routeIs('syltjunkie.entities.*') || request()->routeIs('syltjunkie.entity.*')"
        />
        <x-ui-page-sidebar-item
            :href="route('syltjunkie.entity-types.index')"
            icon="heroicon-o-tag"
            label="Entity Types"
            :active="request()->routeIs('syltjunkie.entity-types.*')"
        />
    </x-ui-page-sidebar-group>
</x-ui-page-sidebar>
