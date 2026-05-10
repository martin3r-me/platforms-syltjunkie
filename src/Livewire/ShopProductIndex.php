<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Platform\Syltjunkie\Models\SjShopProduct;

class ShopProductIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterStatus = '';
    public string $filterType = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterType(): void
    {
        $this->resetPage();
    }

    public function archiveProduct(int $id): void
    {
        $team = Auth::user()->currentTeam;
        $product = SjShopProduct::where('team_id', $team->id)->findOrFail($id);
        $product->update(['status' => 'archived']);
    }

    public function duplicateProduct(int $id): void
    {
        $team = Auth::user()->currentTeam;
        $product = SjShopProduct::where('team_id', $team->id)->findOrFail($id);

        $newProduct = $product->replicate(['uuid', 'slug']);
        $newProduct->name = $product->name . ' (Kopie)';
        $newProduct->status = 'draft';
        $newProduct->save();

        // Duplicate variants
        foreach ($product->variants as $variant) {
            $newVariant = $variant->replicate();
            $newVariant->product_id = $newProduct->id;
            $newVariant->save();
        }
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $query = SjShopProduct::where('team_id', $team->id)
            ->with(['primaryImage.contextFile.variants'])
            ->withCount('variants');

        if ($this->search) {
            $query->where('name', 'like', "%{$this->search}%");
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterType) {
            $query->where('product_type', $this->filterType);
        }

        $products = $query->orderBy('sort_order')->orderBy('name')->paginate(25);

        return view('syltjunkie::livewire.shop-product-index', [
            'products' => $products,
        ])->layout('platform::layouts.app');
    }
}
