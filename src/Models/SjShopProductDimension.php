<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SjShopProductDimension extends Model
{
    use SoftDeletes;

    protected $table = 'sj_shop_product_dimensions';

    protected $fillable = [
        'product_id',
        'name',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(SjShopProduct::class, 'product_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(SjShopProductDimensionValue::class, 'dimension_id')->orderBy('sort_order');
    }
}
