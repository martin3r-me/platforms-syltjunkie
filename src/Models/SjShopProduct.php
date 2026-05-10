<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Platform\Core\Models\ContextFile;
use Symfony\Component\Uid\UuidV7;

class SjShopProduct extends Model
{
    use SoftDeletes;

    protected $table = 'sj_shop_products';

    protected $fillable = [
        'team_id',
        'name',
        'slug',
        'description',
        'product_type',
        'price_cents',
        'compare_at_price_cents',
        'currency',
        'status',
        'is_featured',
        'stock_quantity',
        'sort_order',
        'digital_file_id',
        'extra_fields',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'extra_fields' => 'array',
        'price_cents' => 'integer',
        'compare_at_price_cents' => 'integer',
        'stock_quantity' => 'integer',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }

            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    // ── Relationships ──────────────────────────────────────

    public function variants(): HasMany
    {
        return $this->hasMany(SjShopProductVariant::class, 'product_id')->orderBy('sort_order');
    }

    public function dimensions(): HasMany
    {
        return $this->hasMany(SjShopProductDimension::class, 'product_id')->orderBy('sort_order');
    }

    public function images(): BelongsToMany
    {
        return $this->belongsToMany(SjImage::class, 'sj_shop_product_images', 'product_id', 'sj_image_id')
            ->withPivot(['sort_order', 'is_primary'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function primaryImage(): BelongsToMany
    {
        return $this->images()->wherePivot('is_primary', true);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(SjShopOrderItem::class, 'product_id');
    }

    public function digitalFile(): BelongsTo
    {
        return $this->belongsTo(ContextFile::class, 'digital_file_id');
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePhysical($query)
    {
        return $query->where('product_type', 'physical');
    }

    public function scopeDigital($query)
    {
        return $query->where('product_type', 'digital');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    // ── Accessors ──────────────────────────────────────────

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price_cents / 100, 2, ',', '.') . ' €';
    }

    public function getHasVariantsAttribute(): bool
    {
        return $this->variants()->exists();
    }

    public function getInStockAttribute(): bool
    {
        if ($this->product_type === 'digital') {
            return true;
        }

        if ($this->stock_quantity === null) {
            return true;
        }

        if ($this->variants()->exists()) {
            return $this->variants()->where('is_active', true)->where('stock_quantity', '>', 0)->exists();
        }

        return $this->stock_quantity > 0;
    }
}
