<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SjShopProductDimensionValue extends Model
{
    use SoftDeletes;

    protected $table = 'sj_shop_product_dimension_values';

    protected $fillable = [
        'dimension_id',
        'value',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(SjShopProductDimension::class, 'dimension_id');
    }
}
