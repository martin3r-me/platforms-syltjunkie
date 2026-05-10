<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SjShopProductVariant extends Model
{
    protected $table = 'sj_shop_product_variants';

    protected $fillable = [
        'product_id',
        'label',
        'sku',
        'price_cents',
        'stock_quantity',
        'sort_order',
        'options',
        'is_active',
    ];

    protected $casts = [
        'options' => 'array',
        'is_active' => 'boolean',
        'price_cents' => 'integer',
        'stock_quantity' => 'integer',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(SjShopProduct::class, 'product_id');
    }

    public function dimensionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            SjShopProductDimensionValue::class,
            'sj_shop_variant_dimension_values',
            'variant_id',
            'dimension_value_id'
        )->withTimestamps();
    }

    public function getVariantNameAttribute(): string
    {
        if ($this->relationLoaded('dimensionValues')) {
            return $this->dimensionValues
                ->sortBy(fn ($dv) => $dv->dimension->sort_order ?? 0)
                ->pluck('value')
                ->implode(' / ');
        }

        return $this->label;
    }

    public function getEffectivePriceCentsAttribute(): int
    {
        return $this->price_cents ?? $this->product->price_cents;
    }
}
