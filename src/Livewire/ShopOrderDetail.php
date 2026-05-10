<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Syltjunkie\Models\SjShopOrder;

class ShopOrderDetail extends Component
{
    public SjShopOrder $order;
    public string $notes = '';

    public function mount(SjShopOrder $order): void
    {
        abort_unless($order->team_id === Auth::user()->currentTeam->id, 403);
        $this->order = $order;
        $this->notes = $order->notes ?? '';
    }

    public function updateStatus(string $newStatus): void
    {
        $allowed = ['pending', 'paid', 'shipped', 'completed', 'cancelled', 'refunded'];
        if (!in_array($newStatus, $allowed)) return;

        $updates = ['status' => $newStatus];

        if ($newStatus === 'paid' && !$this->order->paid_at) {
            $updates['paid_at'] = now();
        }
        if ($newStatus === 'shipped' && !$this->order->shipped_at) {
            $updates['shipped_at'] = now();
        }

        $this->order->update($updates);
        $this->order->refresh();
    }

    public function saveNotes(): void
    {
        $this->order->update(['notes' => $this->notes ?: null]);
        session()->flash('success', 'Notizen gespeichert.');
    }

    public function render()
    {
        $this->order->load([
            'items.product',
            'items.variant',
        ]);

        return view('syltjunkie::livewire.shop-order-detail', [
            'order' => $this->order,
        ])->layout('platform::layouts.app');
    }
}
