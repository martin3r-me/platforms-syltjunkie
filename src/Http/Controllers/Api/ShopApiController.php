<?php

namespace Platform\Syltjunkie\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Platform\Core\Http\Controllers\ApiController;
use Platform\Core\Services\ContextFileService;
use Platform\Syltjunkie\Http\Controllers\Api\Concerns\ResolvesPublicTeam;
use Platform\Syltjunkie\Models\SjShopOrder;
use Platform\Syltjunkie\Models\SjShopProduct;
use Platform\Syltjunkie\Models\SjShopProductVariant;

class ShopApiController extends ApiController
{
    use ResolvesPublicTeam;

    public function products(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        $query = SjShopProduct::where('team_id', $teamId)
            ->active()
            ->with([
                'variants' => fn ($q) => $q->where('is_active', true),
                'dimensions' => fn ($q) => $q->orderBy('sort_order'),
                'primaryImage.contextFile.variants',
            ]);

        if ($type = $request->query('type')) {
            if (in_array($type, ['physical', 'digital'])) {
                $query->where('product_type', $type);
            }
        }

        if ($request->boolean('featured')) {
            $query->featured();
        }

        $sortField = $request->query('sort', 'sort_order');
        match ($sortField) {
            'price' => $query->orderBy('price_cents'),
            'name' => $query->orderBy('name'),
            'newest' => $query->orderByDesc('created_at'),
            default => $query->orderBy('sort_order')->orderBy('name'),
        };

        $products = $query->get();

        $data = $products->map(fn (SjShopProduct $product) => [
            'id' => $product->id,
            'uuid' => $product->uuid,
            'slug' => $product->slug,
            'name' => $product->name,
            'description' => $product->description,
            'product_type' => $product->product_type,
            'price_cents' => $product->price_cents,
            'formatted_price' => $product->formatted_price,
            'compare_at_price_cents' => $product->compare_at_price_cents,
            'currency' => $product->currency,
            'is_featured' => $product->is_featured,
            'in_stock' => $product->in_stock,
            'variant_dimensions' => $product->dimensions->pluck('name')->values(),
            'primary_image' => $this->formatImage($product->primaryImage->first()),
            'variants' => $product->variants->map(fn ($v) => [
                'id' => $v->id,
                'label' => $v->label,
                'sku' => $v->sku,
                'price_cents' => $v->effective_price_cents,
                'stock_quantity' => $v->stock_quantity,
                'available' => $v->stock_quantity === null || $v->stock_quantity > 0,
                'options' => $v->options,
                'is_active' => $v->is_active,
            ])->values(),
            'extra_fields' => $product->extra_fields,
        ])->values();

        return $this->success($data);
    }

    public function product(Request $request, string $slug): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        $product = SjShopProduct::where('team_id', $teamId)
            ->where('slug', $slug)
            ->active()
            ->with([
                'variants' => fn ($q) => $q->where('is_active', true),
                'dimensions' => fn ($q) => $q->orderBy('sort_order'),
                'images.contextFile.variants',
            ])
            ->first();

        if (!$product) {
            return $this->notFound('Produkt nicht gefunden.');
        }

        $data = [
            'id' => $product->id,
            'uuid' => $product->uuid,
            'slug' => $product->slug,
            'name' => $product->name,
            'description' => $product->description,
            'product_type' => $product->product_type,
            'price_cents' => $product->price_cents,
            'formatted_price' => $product->formatted_price,
            'compare_at_price_cents' => $product->compare_at_price_cents,
            'currency' => $product->currency,
            'is_featured' => $product->is_featured,
            'in_stock' => $product->in_stock,
            'variant_dimensions' => $product->dimensions->pluck('name')->values(),
            'images' => $product->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => $img->url,
                'thumbnail_url' => $img->thumbnail_url,
                'title' => $img->title,
                'is_primary' => (bool) $img->pivot->is_primary,
            ])->values(),
            'variants' => $product->variants->map(fn ($v) => [
                'id' => $v->id,
                'label' => $v->label,
                'sku' => $v->sku,
                'price_cents' => $v->effective_price_cents,
                'stock_quantity' => $v->stock_quantity,
                'available' => $v->stock_quantity === null || $v->stock_quantity > 0,
                'options' => $v->options,
                'is_active' => $v->is_active,
            ])->values(),
            'extra_fields' => $product->extra_fields,
        ];

        return $this->success($data);
    }

    public function createOrder(Request $request): JsonResponse
    {
        $teamId = $this->resolveTeamId($request);

        $validated = $request->validate([
            'customer_email' => 'required|email|max:255',
            'customer_name' => 'required|string|max:255',
            'shipping_address' => 'nullable|array',
            'shipping_address.street' => 'required_with:shipping_address|string',
            'shipping_address.city' => 'required_with:shipping_address|string',
            'shipping_address.zip' => 'required_with:shipping_address|string',
            'shipping_address.country' => 'required_with:shipping_address|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.variant_id' => 'nullable|integer',
            'items.*.quantity' => 'required|integer|min:1|max:99',
            'payment_provider' => 'nullable|string|max:50',
            'payment_reference' => 'nullable|string|max:255',
        ]);

        $shopConfig = config('syltjunkie.shop');
        $hasPhysical = false;
        $subtotalCents = 0;
        $orderItems = [];

        // Validate all items and build order items
        foreach ($validated['items'] as $item) {
            $product = SjShopProduct::where('team_id', $teamId)
                ->active()
                ->find($item['product_id']);

            if (!$product) {
                return $this->error("Produkt #{$item['product_id']} nicht gefunden oder nicht verfügbar.", null, 422);
            }

            $variant = null;
            $unitPriceCents = $product->price_cents;
            $variantLabel = null;

            if (!empty($item['variant_id'])) {
                $variant = $product->variants()
                    ->where('is_active', true)
                    ->find($item['variant_id']);

                if (!$variant) {
                    return $this->error("Variante #{$item['variant_id']} nicht gefunden.", null, 422);
                }

                $unitPriceCents = $variant->effective_price_cents;
                $variantLabel = $variant->label;

                if ($variant->stock_quantity < $item['quantity']) {
                    return $this->error("Nicht genug Bestand für '{$product->name} – {$variant->label}'.", null, 422);
                }
            } elseif ($product->product_type === 'physical' && $product->stock_quantity !== null) {
                if ($product->stock_quantity < $item['quantity']) {
                    return $this->error("Nicht genug Bestand für '{$product->name}'.", null, 422);
                }
            }

            if ($product->product_type === 'physical') {
                $hasPhysical = true;
            }

            $lineTotalCents = $unitPriceCents * $item['quantity'];
            $subtotalCents += $lineTotalCents;

            $orderItems[] = [
                'product' => $product,
                'variant' => $variant,
                'quantity' => $item['quantity'],
                'unit_price_cents' => $unitPriceCents,
                'total_cents' => $lineTotalCents,
                'is_digital' => $product->product_type === 'digital',
                'variant_label' => $variantLabel,
            ];
        }

        // Shipping
        $shippingCents = 0;
        if ($hasPhysical) {
            $shippingCents = $shopConfig['shipping_flat_cents'] ?? 490;
            $freeShippingFrom = $shopConfig['free_shipping_from_cents'] ?? 5000;
            if ($subtotalCents >= $freeShippingFrom) {
                $shippingCents = 0;
            }
        }

        $totalCents = $subtotalCents + $shippingCents;

        // Create order in transaction
        $order = DB::transaction(function () use ($teamId, $validated, $subtotalCents, $shippingCents, $totalCents, $orderItems, $shopConfig) {
            $order = SjShopOrder::create([
                'team_id' => $teamId,
                'status' => 'pending',
                'customer_email' => $validated['customer_email'],
                'customer_name' => $validated['customer_name'],
                'shipping_address' => $validated['shipping_address'] ?? null,
                'subtotal_cents' => $subtotalCents,
                'shipping_cents' => $shippingCents,
                'total_cents' => $totalCents,
                'currency' => $shopConfig['currency'] ?? 'EUR',
                'payment_provider' => $validated['payment_provider'] ?? null,
                'payment_reference' => $validated['payment_reference'] ?? null,
            ]);

            foreach ($orderItems as $item) {
                $downloadUrl = null;
                if ($item['is_digital'] && $item['product']->digitalFile) {
                    $downloadUrl = $this->generateDownloadUrl($item['product']->digitalFile);
                }

                $order->items()->create([
                    'product_id' => $item['product']->id,
                    'variant_id' => $item['variant']?->id,
                    'product_name' => $item['product']->name,
                    'variant_label' => $item['variant_label'],
                    'quantity' => $item['quantity'],
                    'unit_price_cents' => $item['unit_price_cents'],
                    'total_cents' => $item['total_cents'],
                    'is_digital' => $item['is_digital'],
                    'download_url' => $downloadUrl,
                ]);

                // Decrement stock
                if ($item['variant']) {
                    $item['variant']->decrement('stock_quantity', $item['quantity']);
                } elseif ($item['product']->stock_quantity !== null) {
                    $item['product']->decrement('stock_quantity', $item['quantity']);
                }
            }

            return $order;
        });

        $order->load('items');

        $downloadLinks = $order->items
            ->where('is_digital', true)
            ->whereNotNull('download_url')
            ->map(fn ($item) => [
                'product_name' => $item->product_name,
                'download_url' => $item->download_url,
            ])->values();

        return $this->created([
            'order_number' => $order->order_number,
            'uuid' => $order->uuid,
            'total_cents' => $order->total_cents,
            'formatted_total' => $order->formatted_total,
            'currency' => $order->currency,
            'status' => $order->status,
            'download_links' => $downloadLinks,
        ]);
    }

    protected function formatImage($image): ?array
    {
        if (!$image) {
            return null;
        }

        return [
            'url' => $image->url,
            'thumbnail_url' => $image->thumbnail_url,
            'title' => $image->title,
        ];
    }

    protected function generateDownloadUrl($contextFile): string
    {
        return ContextFileService::generateUrl(
            $contextFile->disk,
            $contextFile->path,
            $contextFile->token,
            'core.context-files.download'
        );
    }
}
