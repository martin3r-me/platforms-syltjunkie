<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Core\Models\ContextFile;
use Platform\Core\Services\ContextFileService;
use Platform\Syltjunkie\Models\SjImage;
use Platform\Syltjunkie\Models\SjShopProduct;
use Platform\Syltjunkie\Models\SjShopProductDimension;
use Platform\Syltjunkie\Models\SjShopProductDimensionValue;
use Platform\Syltjunkie\Models\SjShopProductVariant;

class ShopProductEditor extends Component
{
    use WithFileUploads;

    public ?int $productId = null;

    // Form fields
    public string $name = '';
    public string $slug = '';
    public string $description = '';
    public string $productType = 'physical';
    public int $priceCents = 0;
    public ?int $compareAtPriceCents = null;
    public string $status = 'draft';
    public bool $isFeatured = false;
    public ?int $stockQuantity = null;
    public string $skuPrefix = '';

    // Dimensions (physical only)
    public string $newDimensionName = '';
    public array $newDimensionValueInputs = []; // [dimensionId => 'input text']

    // Variant overrides: [variantId => ['sku' => ..., 'price_cents' => ..., 'stock_quantity' => ..., 'is_active' => ...]]
    public array $variantOverrides = [];

    // Digital file
    public ?int $digitalFileId = null;
    public $digitalFileUpload;

    // Images
    public $imageUpload;
    public string $imageSearch = '';

    // Extra fields
    public array $extraFields = [];
    public string $newExtraKey = '';
    public string $newExtraValue = '';

    public function mount(?int $product = null): void
    {
        if ($product) {
            $this->loadProduct($product);
        }
    }

    protected function loadProduct(int $id): void
    {
        $team = Auth::user()->currentTeam;
        $product = SjShopProduct::where('team_id', $team->id)->findOrFail($id);

        $this->productId = $product->id;
        $this->name = $product->name;
        $this->slug = $product->slug;
        $this->description = $product->description ?? '';
        $this->productType = $product->product_type;
        $this->priceCents = $product->price_cents;
        $this->compareAtPriceCents = $product->compare_at_price_cents;
        $this->status = $product->status;
        $this->isFeatured = $product->is_featured;
        $this->stockQuantity = $product->stock_quantity;
        $this->digitalFileId = $product->digital_file_id;
        $this->extraFields = $product->extra_fields ?? [];

        // Load variant overrides
        foreach ($product->variants as $v) {
            $this->variantOverrides[$v->id] = [
                'sku' => $v->sku ?? '',
                'price_cents' => $v->price_cents,
                'stock_quantity' => $v->stock_quantity,
                'is_active' => $v->is_active,
            ];
        }
    }

    public function updatedName(): void
    {
        if (!$this->productId && !$this->slug) {
            $this->slug = Str::slug($this->name);
        }
    }

    // ── Dimensions ─────────────────────────────────────────

    public function addDimension(): void
    {
        $name = trim($this->newDimensionName);
        if (!$name || !$this->productId) return;

        $product = $this->getProduct();
        $product->dimensions()->create([
            'name' => $name,
            'sort_order' => $product->dimensions()->count(),
        ]);

        $this->newDimensionName = '';
        $this->rebuildVariantsMatrix();
    }

    public function removeDimension(int $dimensionId): void
    {
        if (!$this->productId) return;

        $product = $this->getProduct();
        $dimension = $product->dimensions()->findOrFail($dimensionId);
        $dimension->values()->forceDelete();
        $dimension->forceDelete();

        $this->rebuildVariantsMatrix();
    }

    public function addDimensionValue(int $dimensionId): void
    {
        $input = trim($this->newDimensionValueInputs[$dimensionId] ?? '');
        if (!$input) return;

        $dimension = SjShopProductDimension::findOrFail($dimensionId);
        $dimension->values()->create([
            'value' => $input,
            'sort_order' => $dimension->values()->count(),
        ]);

        $this->newDimensionValueInputs[$dimensionId] = '';
        $this->rebuildVariantsMatrix();
    }

    public function removeDimensionValue(int $valueId): void
    {
        $value = SjShopProductDimensionValue::findOrFail($valueId);
        $value->forceDelete();

        $this->rebuildVariantsMatrix();
    }

    protected function rebuildVariantsMatrix(): void
    {
        if (!$this->productId) return;

        $product = $this->getProduct();
        $dimensions = $product->dimensions()->with('values')->orderBy('sort_order')->get();

        if ($dimensions->isEmpty() || $dimensions->every(fn ($d) => $d->values->isEmpty())) {
            $product->variants()->delete();
            $this->variantOverrides = [];
            return;
        }

        // Backup current variant data (keyed by combination of dimension value IDs)
        $backup = [];
        foreach ($product->variants()->with('dimensionValues')->get() as $variant) {
            $comboKey = $variant->dimensionValues->pluck('id')->sort()->implode('-');
            $backup[$comboKey] = [
                'sku' => $variant->sku,
                'price_cents' => $variant->price_cents,
                'stock_quantity' => $variant->stock_quantity,
                'is_active' => $variant->is_active,
            ];
        }

        // Delete existing variants
        $product->variants()->delete();

        // Generate all combinations
        $valueSets = $dimensions
            ->filter(fn ($d) => $d->values->isNotEmpty())
            ->map(fn ($d) => $d->values->pluck('id')->toArray())
            ->values()
            ->toArray();

        $combinations = $this->generateCombinations($valueSets);

        // Build a lookup to find dimension values for labels
        $allValues = SjShopProductDimensionValue::with('dimension')
            ->whereIn('dimension_id', $dimensions->pluck('id'))
            ->get()
            ->keyBy('id');

        $this->variantOverrides = [];
        $skuBase = $this->skuPrefix ?: Str::upper(Str::substr(Str::slug($this->name, ''), 0, 6));

        foreach ($combinations as $index => $combo) {
            // Build label from dimension values
            $labelParts = [];
            $options = [];
            foreach ($combo as $valueId) {
                $dv = $allValues[$valueId] ?? null;
                if ($dv) {
                    $labelParts[] = $dv->value;
                    $options[$dv->dimension->name] = $dv->value;
                }
            }
            $label = implode(' / ', $labelParts);

            // Try to restore backup
            $comboKey = collect($combo)->sort()->implode('-');
            $restored = $backup[$comboKey] ?? null;

            $sku = $restored['sku'] ?? sprintf('%s-%03d', $skuBase, $index + 1);

            $variant = $product->variants()->create([
                'label' => $label,
                'sku' => $sku,
                'price_cents' => $restored['price_cents'] ?? null,
                'stock_quantity' => $restored['stock_quantity'] ?? 0,
                'sort_order' => $index,
                'options' => $options,
                'is_active' => $restored['is_active'] ?? true,
            ]);

            // Attach dimension values
            $variant->dimensionValues()->attach($combo);

            $this->variantOverrides[$variant->id] = [
                'sku' => $variant->sku ?? '',
                'price_cents' => $variant->price_cents,
                'stock_quantity' => $variant->stock_quantity,
                'is_active' => $variant->is_active,
            ];
        }
    }

    protected function generateCombinations(array $arrays): array
    {
        if (empty($arrays)) return [];

        $result = [[]];
        foreach ($arrays as $array) {
            $append = [];
            foreach ($result as $product) {
                foreach ($array as $item) {
                    $append[] = array_merge($product, [$item]);
                }
            }
            $result = $append;
        }

        return $result;
    }

    public function saveVariantOverrides(): void
    {
        if (!$this->productId) return;

        $product = $this->getProduct();
        foreach ($this->variantOverrides as $variantId => $data) {
            $product->variants()->where('id', $variantId)->update([
                'sku' => $data['sku'] ?: null,
                'price_cents' => $data['price_cents'] ?: null,
                'stock_quantity' => $data['stock_quantity'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
            ]);
        }

        session()->flash('success', 'Varianten gespeichert.');
    }

    // ── Extra Fields ───────────────────────────────────────

    public function addExtraField(): void
    {
        $key = trim($this->newExtraKey);
        $value = trim($this->newExtraValue);
        if (!$key) return;

        $this->extraFields[$key] = $value;
        $this->newExtraKey = '';
        $this->newExtraValue = '';
    }

    public function removeExtraField(string $key): void
    {
        unset($this->extraFields[$key]);
    }

    // ── Digital File ───────────────────────────────────────

    public function uploadDigitalFile(): void
    {
        $this->validate([
            'digitalFileUpload' => 'required|file|max:102400',
        ]);

        $team = Auth::user()->currentTeam;
        $service = app(ContextFileService::class);

        $result = $service->uploadForContext(
            $this->digitalFileUpload,
            SjShopProduct::class,
            0,
            [
                'team_id' => $team->id,
                'user_id' => Auth::id(),
                'folder' => 'syltjunkie/shop',
            ]
        );

        $this->digitalFileId = $result['id'];
        $this->digitalFileUpload = null;
    }

    public function removeDigitalFile(): void
    {
        $this->digitalFileId = null;
    }

    // ── Images ─────────────────────────────────────────────

    public function uploadImage(): void
    {
        $this->validate([
            'imageUpload' => 'required|image|max:20480',
        ]);

        $team = Auth::user()->currentTeam;
        $service = app(ContextFileService::class);

        $result = $service->uploadForContext(
            $this->imageUpload,
            SjImage::class,
            0,
            [
                'team_id' => $team->id,
                'user_id' => Auth::id(),
                'folder' => 'syltjunkie',
                'generate_variants' => true,
            ]
        );

        $image = SjImage::create([
            'team_id' => $team->id,
            'context_file_id' => $result['id'],
            'title' => pathinfo($this->imageUpload->getClientOriginalName(), PATHINFO_FILENAME),
        ]);

        if ($this->productId) {
            $product = SjShopProduct::find($this->productId);
            $hasPrimary = $product->images()->wherePivot('is_primary', true)->exists();
            $product->images()->attach($image->id, [
                'sort_order' => $product->images()->count(),
                'is_primary' => !$hasPrimary,
            ]);
        }

        $this->imageUpload = null;
    }

    public function attachImage(int $imageId): void
    {
        if (!$this->productId) return;

        $team = Auth::user()->currentTeam;
        $product = SjShopProduct::where('team_id', $team->id)->findOrFail($this->productId);

        if ($product->images()->where('sj_image_id', $imageId)->exists()) return;

        $hasPrimary = $product->images()->wherePivot('is_primary', true)->exists();
        $product->images()->attach($imageId, [
            'sort_order' => $product->images()->count(),
            'is_primary' => !$hasPrimary,
        ]);
    }

    public function detachImage(int $imageId): void
    {
        if (!$this->productId) return;

        $product = SjShopProduct::find($this->productId);
        $product->images()->detach($imageId);
    }

    public function setPrimaryImage(int $imageId): void
    {
        if (!$this->productId) return;

        $product = SjShopProduct::find($this->productId);
        $product->images()->updateExistingPivot(
            $product->images->pluck('id')->toArray(),
            ['is_primary' => false]
        );
        $product->images()->updateExistingPivot($imageId, ['is_primary' => true]);
    }

    // ── Save ───────────────────────────────────────────────

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'productType' => 'required|in:physical,digital',
            'priceCents' => 'required|integer|min:0',
            'status' => 'required|in:draft,active,archived',
        ]);

        $team = Auth::user()->currentTeam;

        $data = [
            'team_id' => $team->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description ?: null,
            'product_type' => $this->productType,
            'price_cents' => $this->priceCents,
            'compare_at_price_cents' => $this->compareAtPriceCents ?: null,
            'status' => $this->status,
            'is_featured' => $this->isFeatured,
            'stock_quantity' => $this->productType === 'physical' ? $this->stockQuantity : null,
            'digital_file_id' => $this->productType === 'digital' ? $this->digitalFileId : null,
            'extra_fields' => !empty($this->extraFields) ? $this->extraFields : null,
        ];

        if ($this->productId) {
            $product = SjShopProduct::where('team_id', $team->id)->findOrFail($this->productId);
            $product->update($data);
        } else {
            $product = SjShopProduct::create($data);
            $this->productId = $product->id;
        }

        // Save variant overrides (SKU, price, stock, active)
        if ($this->productType === 'physical') {
            foreach ($this->variantOverrides as $variantId => $overrides) {
                $product->variants()->where('id', $variantId)->update([
                    'sku' => $overrides['sku'] ?: null,
                    'price_cents' => $overrides['price_cents'] ?: null,
                    'stock_quantity' => $overrides['stock_quantity'] ?? 0,
                    'is_active' => $overrides['is_active'] ?? true,
                ]);
            }
        } else {
            // Digital: remove all dimensions and variants
            $product->dimensions()->each(function ($d) {
                $d->values()->forceDelete();
                $d->forceDelete();
            });
            $product->variants()->delete();
            $this->variantOverrides = [];
        }

        session()->flash('success', 'Produkt gespeichert.');

        return $this->redirect(route('syltjunkie.shop.products.edit', $product), navigate: true);
    }

    // ── Helpers ─────────────────────────────────────────────

    protected function getProduct(): SjShopProduct
    {
        $team = Auth::user()->currentTeam;
        return SjShopProduct::where('team_id', $team->id)->findOrFail($this->productId);
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        // Available images for attachment
        $imageQuery = SjImage::where('team_id', $team->id)
            ->with('contextFile.variants')
            ->orderByDesc('created_at');

        if ($this->imageSearch) {
            $imageQuery->where('title', 'like', "%{$this->imageSearch}%");
        }

        $availableImages = $imageQuery->limit(24)->get();

        // Product images (if editing)
        $productImages = collect();
        $dimensions = collect();
        $variants = collect();

        if ($this->productId) {
            $product = SjShopProduct::find($this->productId);
            $productImages = $product->images()->with('contextFile.variants')->get();
            $dimensions = $product->dimensions()->with('values')->orderBy('sort_order')->get();
            $variants = $product->variants()
                ->with('dimensionValues.dimension')
                ->orderBy('sort_order')
                ->get();
        }

        // Digital file info
        $digitalFile = $this->digitalFileId ? ContextFile::find($this->digitalFileId) : null;

        return view('syltjunkie::livewire.shop-product-editor', [
            'availableImages' => $availableImages,
            'productImages' => $productImages,
            'digitalFile' => $digitalFile,
            'dimensions' => $dimensions,
            'variants' => $variants,
        ])->layout('platform::layouts.app');
    }
}
