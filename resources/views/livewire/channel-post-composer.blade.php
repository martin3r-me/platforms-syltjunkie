<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Syltjunkie', 'href' => route('syltjunkie.dashboard'), 'icon' => 'globe-alt'],
            ['label' => 'Posts', 'href' => route('syltjunkie.posts.index')],
            ['label' => 'Erstellen'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-[13px] text-red-700">
            {{ session('error') }}
        </div>
        @endif
        @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-3 text-[13px] text-green-700">
            {{ session('success') }}
        </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Left: Composer --}}
            <div class="lg:col-span-2 space-y-5">
                <h1 class="text-xl font-semibold text-gray-900">Post erstellen</h1>

                {{-- Channel + Entity --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] text-gray-400 uppercase tracking-wider mb-1">Channel</label>
                        <select wire:model.live="channelId"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <option value="">-- Channel wählen --</option>
                            @foreach($channels as $ch)
                                <option value="{{ $ch->id }}">{{ $ch->name }} ({{ $ch->type }})</option>
                            @endforeach
                        </select>
                        @error('channelId') <span class="text-[11px] text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-[11px] text-gray-400 uppercase tracking-wider mb-1">Entity (optional)</label>
                        <select wire:model.live="entityId"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <option value="">-- Keine --</option>
                            @foreach($entities as $ent)
                                <option value="{{ $ent->id }}">{{ $ent->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Post Type --}}
                <div>
                    <label class="block text-[11px] text-gray-400 uppercase tracking-wider mb-1">Post-Typ</label>
                    <div class="flex items-center gap-2">
                        @foreach(['image' => 'Image', 'carousel' => 'Carousel', 'reel' => 'Reel'] as $value => $label)
                        <button wire:click="$set('postType', '{{ $value }}')"
                            class="rounded-lg px-3 py-1.5 text-[12px] font-medium border
                                {{ $postType === $value ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-500 hover:bg-gray-50' }}">
                            {{ $label }}
                        </button>
                        @endforeach
                    </div>
                </div>

                {{-- Caption --}}
                <div>
                    <label class="block text-[11px] text-gray-400 uppercase tracking-wider mb-1">Caption</label>
                    <textarea wire:model="caption" rows="5" placeholder="Post-Text eingeben..."
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"></textarea>
                    @error('caption') <span class="text-[11px] text-red-500">{{ $message }}</span> @enderror
                </div>

                {{-- Hashtags --}}
                <div>
                    <label class="block text-[11px] text-gray-400 uppercase tracking-wider mb-1">Hashtags (komma-getrennt)</label>
                    <input type="text" wire:model="hashtagsInput" placeholder="#sylt, #nordsee, #urlaub"
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" />
                </div>

                {{-- Image Picker --}}
                <div>
                    <label class="block text-[11px] text-gray-400 uppercase tracking-wider mb-1">
                        Bilder ({{ count($selectedImageIds) }} ausgewählt)
                    </label>
                    <input type="text" wire:model.live.debounce.300ms="imageSearch" placeholder="Bilder suchen..."
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-[13px] mb-2 focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" />
                    <div class="grid grid-cols-4 sm:grid-cols-6 gap-2 max-h-64 overflow-y-auto p-1">
                        @foreach($images as $image)
                        <button wire:click="toggleImage({{ $image->id }})" type="button"
                            class="relative aspect-square rounded-lg overflow-hidden border-2 transition-colors
                                {{ in_array($image->id, $selectedImageIds) ? 'border-blue-500 ring-2 ring-blue-200' : 'border-transparent hover:border-gray-300' }}">
                            <img src="{{ $image->thumbnail_url }}" alt="{{ $image->title }}" class="w-full h-full object-cover" loading="lazy" />
                            @if(in_array($image->id, $selectedImageIds))
                            <div class="absolute top-1 right-1 w-5 h-5 rounded-full bg-blue-500 text-white flex items-center justify-center text-[10px] font-bold">
                                {{ array_search($image->id, $selectedImageIds) + 1 }}
                            </div>
                            @endif
                        </button>
                        @endforeach
                    </div>
                </div>

                {{-- Schedule --}}
                <div>
                    <label class="block text-[11px] text-gray-400 uppercase tracking-wider mb-1">Zeitplanung</label>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 text-[13px] text-gray-700">
                            <input type="radio" wire:model.live="publishNow" value="1" class="text-blue-600" />
                            Jetzt
                        </label>
                        <label class="flex items-center gap-2 text-[13px] text-gray-700">
                            <input type="radio" wire:model.live="publishNow" value="0" class="text-blue-600" />
                            Termin
                        </label>
                    </div>
                    @if(!$publishNow)
                    <input type="datetime-local" wire:model="scheduledAt"
                        class="mt-2 rounded-lg border border-gray-200 px-3 py-2 text-[13px] focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" />
                    @endif
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-3 pt-2">
                    <button wire:click="saveDraft"
                        class="rounded-lg border border-gray-200 px-4 py-2 text-[13px] font-medium text-gray-600 hover:bg-gray-50">
                        Als Entwurf speichern
                    </button>
                    @if(!$publishNow)
                    <button wire:click="schedulePost"
                        class="rounded-lg bg-yellow-500 px-4 py-2 text-[13px] font-medium text-white hover:bg-yellow-600">
                        Planen
                    </button>
                    @else
                    <button wire:click="publishPost" wire:confirm="Post jetzt veröffentlichen?"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-[13px] font-medium text-white hover:bg-blue-700">
                        <span wire:loading.remove wire:target="publishPost">Jetzt veröffentlichen</span>
                        <span wire:loading wire:target="publishPost">Wird veröffentlicht...</span>
                    </button>
                    @endif
                </div>
            </div>

            {{-- Right: Preview --}}
            <div class="space-y-4">
                <h2 class="text-[13px] font-semibold text-gray-700">Vorschau</h2>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    @if(count($selectedImageIds))
                    <div class="aspect-square rounded-lg overflow-hidden bg-gray-100 mb-3">
                        @php
                            $previewImage = $images->firstWhere('id', $selectedImageIds[0]);
                        @endphp
                        @if($previewImage)
                            <img src="{{ $previewImage->thumbnail_url }}" alt="" class="w-full h-full object-cover" />
                        @endif
                    </div>
                    @if(count($selectedImageIds) > 1)
                    <div class="flex justify-center gap-1 mb-2">
                        @foreach($selectedImageIds as $i => $imgId)
                        <span class="w-1.5 h-1.5 rounded-full {{ $i === 0 ? 'bg-blue-500' : 'bg-gray-300' }}"></span>
                        @endforeach
                    </div>
                    @endif
                    @else
                    <div class="aspect-square rounded-lg bg-gray-100 flex items-center justify-center mb-3">
                        <span class="text-[12px] text-gray-400">Kein Bild ausgewählt</span>
                    </div>
                    @endif

                    <div class="text-[13px] text-gray-900 whitespace-pre-line">{{ $caption ?: 'Noch kein Text...' }}</div>

                    @if($hashtagsInput)
                    <div class="text-[12px] text-blue-600 mt-2">{{ $hashtagsInput }}</div>
                    @endif
                </div>
            </div>
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:syltjunkie.sidebar />
    </x-slot>
</x-ui-page>
