<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjWeather;

class WeatherIndex extends Component
{
    public ?string $selectedSlug = null;

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $entities = SjEntity::where('team_id', $team->id)
            ->where('is_active', true)
            ->whereHas('entityType', fn ($q) => $q->where('code', 'ort'))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('name')
            ->get();

        // Load today's current weather for all entities
        $entityIds = $entities->pluck('id');
        $currentWeather = SjWeather::whereIn('entity_id', $entityIds)
            ->where('record_type', 'current')
            ->where('date', today())
            ->get()
            ->keyBy('entity_id');

        // Load forecast for selected entity (or first one)
        $selectedEntity = null;
        $forecast = collect();
        $currentDetail = null;

        if ($entities->isNotEmpty()) {
            $selectedEntity = $this->selectedSlug
                ? $entities->firstWhere('slug', $this->selectedSlug)
                : $entities->first();

            if ($selectedEntity) {
                $this->selectedSlug = $selectedEntity->slug;

                $currentDetail = SjWeather::forEntity($selectedEntity->id)
                    ->current()
                    ->where('date', today())
                    ->first();

                $forecast = SjWeather::forEntity($selectedEntity->id)
                    ->forecast()
                    ->orderBy('date')
                    ->get();
            }
        }

        $lastFetch = SjWeather::where('team_id', $team->id)
            ->latest('updated_at')
            ->value('updated_at');

        return view('syltjunkie::livewire.weather-index', [
            'entities' => $entities,
            'currentWeather' => $currentWeather,
            'selectedEntity' => $selectedEntity,
            'currentDetail' => $currentDetail,
            'forecast' => $forecast,
            'lastFetch' => $lastFetch,
        ])->layout('platform::layouts.app');
    }
}
