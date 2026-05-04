<div>
    <div x-show="!collapsed" class="px-3 pt-3 pb-2 border-b border-[#2C3135] mb-2">
        <span class="text-[10px] uppercase tracking-widest text-gray-500 font-medium">Syltjunkie</span>
    </div>

    {{-- Entity Graph --}}
    <div x-show="!collapsed" class="px-2 mb-1">
        <div class="px-2 py-1.5 text-[10px] uppercase tracking-widest text-gray-500 font-medium">Entity Graph</div>
        <a href="{{ route('syltjunkie.dashboard') }}" wire:navigate class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
            @svg('heroicon-o-globe-alt', 'w-4 h-4')
            <span>Dashboard</span>
        </a>
        <a href="{{ route('syltjunkie.entities.index') }}" wire:navigate class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
            @svg('heroicon-o-building-storefront', 'w-4 h-4')
            <span>Entities</span>
        </a>
        <a href="{{ route('syltjunkie.entity-types.index') }}" wire:navigate class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
            @svg('heroicon-o-tag', 'w-4 h-4')
            <span>Entity Types</span>
        </a>
    </div>

    {{-- Collapsed View --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[#2C3135]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('syltjunkie.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-[#2C3135] transition-colors" title="Dashboard">
                @svg('heroicon-o-globe-alt', 'w-5 h-5')
            </a>
            <a href="{{ route('syltjunkie.entities.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-[#2C3135] transition-colors" title="Entities">
                @svg('heroicon-o-building-storefront', 'w-5 h-5')
            </a>
            <a href="{{ route('syltjunkie.entity-types.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-[#2C3135] transition-colors" title="Entity Types">
                @svg('heroicon-o-tag', 'w-5 h-5')
            </a>
        </div>
    </div>
</div>
