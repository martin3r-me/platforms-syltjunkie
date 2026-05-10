<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SjShopOrderItem extends Model
{
    protected $table = 'sj_shop_order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'product_name',
        'variant_label',
        'quantity',
        'unit_price_cents',
        'total_cents',
        'is_digital',
        'download_url',
    ];

    protected $casts = [
        'is_digital' => 'boolean',
        'quantity' => 'integer',
        'unit_price_cents' => 'integer',
        'total_cents' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(SjShopOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(SjShopProduct::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(SjShopProductVariant::class, 'variant_id');
    }
}
