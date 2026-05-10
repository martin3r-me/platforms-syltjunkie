<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Platform\Syltjunkie\Models\SjShopOrder;

class ShopOrderIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterStatus = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updateStatus(int $orderId, string $newStatus): void
    {
        $team = Auth::user()->currentTeam;
        $order = SjShopOrder::where('team_id', $team->id)->findOrFail($orderId);

        $allowed = ['pending', 'paid', 'shipped', 'completed', 'cancelled', 'refunded'];
        if (!in_array($newStatus, $allowed)) return;

        $updates = ['status' => $newStatus];

        if ($newStatus === 'paid' && !$order->paid_at) {
            $updates['paid_at'] = now();
        }
        if ($newStatus === 'shipped' && !$order->shipped_at) {
            $updates['shipped_at'] = now();
        }

        $order->update($updates);
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $query = SjShopOrder::where('team_id', $team->id)
            ->withCount('items');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('order_number', 'like', "%{$this->search}%")
                  ->orWhere('customer_name', 'like', "%{$this->search}%")
                  ->orWhere('customer_email', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterStatus) {
            $query->byStatus($this->filterStatus);
        }

        $orders = $query->orderByDesc('created_at')->paginate(25);

        return view('syltjunkie::livewire.shop-order-index', [
            'orders' => $orders,
        ])->layout('platform::layouts.app');
    }
}
