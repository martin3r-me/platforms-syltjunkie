<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Syltjunkie\Models\SjEntity;
use Platform\Syltjunkie\Models\SjEntityUrl;
use Platform\Syltjunkie\Models\SjEntityType;
use Platform\Syltjunkie\Models\SjPipelineCard;
use Platform\Syltjunkie\Models\SjPipelineSlot;

class PipelineBoard extends Component
{
    public ?int $editingCardId = null;
    public array $cardForm = [
        'name' => '',
        'url' => '',
        'entity_type_id' => null,
        'latitude' => null,
        'longitude' => null,
        'notes' => '',
    ];

    public function mount(): void
    {
        $team = Auth::user()->currentTeam;

        // Create default slots if none exist
        if (SjPipelineSlot::where('team_id', $team->id)->count() === 0) {
            SjPipelineSlot::create(['team_id' => $team->id, 'name' => 'Idee', 'order' => 1]);
            SjPipelineSlot::create(['team_id' => $team->id, 'name' => 'Recherche', 'order' => 2]);
            SjPipelineSlot::create(['team_id' => $team->id, 'name' => 'Bereit', 'order' => 3]);
        }
    }

    public function createSlot(): void
    {
        $team = Auth::user()->currentTeam;
        $maxOrder = SjPipelineSlot::where('team_id', $team->id)->max('order') ?? 0;

        SjPipelineSlot::create([
            'team_id' => $team->id,
            'name' => 'Neuer Slot',
            'order' => $maxOrder + 1,
        ]);
    }

    public function renameSlot(int $slotId, string $name): void
    {
        $team = Auth::user()->currentTeam;
        $slot = SjPipelineSlot::where('team_id', $team->id)->findOrFail($slotId);
        $slot->update(['name' => trim($name)]);
    }

    public function deleteSlot(int $slotId): void
    {
        $team = Auth::user()->currentTeam;
        $slot = SjPipelineSlot::where('team_id', $team->id)->findOrFail($slotId);

        // Move cards to unassigned
        SjPipelineCard::where('slot_id', $slotId)->update(['slot_id' => null]);
        $slot->delete();
    }

    public function createCard(?int $slotId = null): void
    {
        $team = Auth::user()->currentTeam;

        SjPipelineCard::create([
            'team_id' => $team->id,
            'slot_id' => $slotId,
            'name' => 'Neue Entity',
        ]);
    }

    public function openCardEditor(int $cardId): void
    {
        $team = Auth::user()->currentTeam;
        $card = SjPipelineCard::where('team_id', $team->id)->findOrFail($cardId);

        $this->editingCardId = $card->id;
        $this->cardForm = [
            'name' => $card->name,
            'url' => $card->url ?? '',
            'entity_type_id' => $card->entity_type_id,
            'latitude' => $card->latitude,
            'longitude' => $card->longitude,
            'notes' => $card->notes ?? '',
        ];
    }

    public function saveCard(): void
    {
        $this->validate([
            'cardForm.name' => 'required|string|max:255',
            'cardForm.url' => 'nullable|url|max:2048',
            'cardForm.entity_type_id' => 'nullable|integer|exists:sj_entity_types,id',
            'cardForm.latitude' => 'nullable|numeric|between:-90,90',
            'cardForm.longitude' => 'nullable|numeric|between:-180,180',
            'cardForm.notes' => 'nullable|string',
        ]);

        $team = Auth::user()->currentTeam;
        $card = SjPipelineCard::where('team_id', $team->id)->findOrFail($this->editingCardId);

        $card->update([
            'name' => $this->cardForm['name'],
            'url' => $this->cardForm['url'] ?: null,
            'entity_type_id' => $this->cardForm['entity_type_id'] ?: null,
            'latitude' => $this->cardForm['latitude'],
            'longitude' => $this->cardForm['longitude'],
            'notes' => $this->cardForm['notes'] ?: null,
        ]);

        $this->editingCardId = null;
        $this->cardForm = ['name' => '', 'url' => '', 'entity_type_id' => null, 'latitude' => null, 'longitude' => null, 'notes' => ''];
    }

    public function deleteCard(int $cardId): void
    {
        $team = Auth::user()->currentTeam;
        $card = SjPipelineCard::where('team_id', $team->id)->findOrFail($cardId);
        $card->delete();
    }

    public function updateCardOrder(array $groups): void
    {
        foreach ($groups as $group) {
            $slotId = ($group['value'] === 'null' || (int) $group['value'] === 0)
                ? null
                : (int) $group['value'];

            foreach ($group['items'] as $item) {
                $card = SjPipelineCard::find($item['value']);
                if (!$card) {
                    continue;
                }
                $card->order = $item['order'];
                $card->slot_id = $slotId;
                $card->save();
            }
        }
    }

    public function updateSlotOrder(array $groups): void
    {
        foreach ($groups as $group) {
            $slot = SjPipelineSlot::find($group['value']);
            if ($slot) {
                $slot->order = $group['order'];
                $slot->save();
            }
        }
    }

    public function convertToEntity(int $cardId): void
    {
        $team = Auth::user()->currentTeam;
        $card = SjPipelineCard::where('team_id', $team->id)->active()->findOrFail($cardId);

        if (empty($card->name) || empty($card->entity_type_id)) {
            session()->flash('error', 'Name und Entity-Typ müssen gesetzt sein.');
            return;
        }

        $entity = SjEntity::create([
            'team_id' => $team->id,
            'name' => $card->name,
            'entity_type_id' => $card->entity_type_id,
            'latitude' => $card->latitude,
            'longitude' => $card->longitude,
            'source' => 'manuell',
            'status' => 'aktiv',
            'is_active' => true,
        ]);

        $entity->syncEntityTypes([$card->entity_type_id]);

        if ($card->url) {
            SjEntityUrl::create([
                'team_id' => $team->id,
                'entity_id' => $entity->id,
                'url' => $card->url,
                'platform' => 'website',
                'is_primary' => true,
                'is_active' => true,
            ]);
        }

        $card->update([
            'converted_at' => now(),
            'converted_entity_id' => $entity->id,
        ]);

        session()->flash('success', "Entity \"{$entity->name}\" erstellt.");
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $slots = SjPipelineSlot::where('team_id', $team->id)
            ->with(['cards' => fn($q) => $q->active()->orderBy('order')])
            ->orderBy('order')
            ->get();

        $entityTypes = SjEntityType::orderBy('name')->get();

        return view('syltjunkie::livewire.pipeline-board', [
            'slots' => $slots,
            'entityTypes' => $entityTypes,
        ])->layout('platform::layouts.app');
    }
}
