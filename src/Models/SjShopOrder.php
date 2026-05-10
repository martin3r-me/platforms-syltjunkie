<?php

namespace Platform\Syltjunkie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Uid\UuidV7;

class SjShopOrder extends Model
{
    use SoftDeletes;

    protected $table = 'sj_shop_orders';

    protected $fillable = [
        'team_id',
        'order_number',
        'status',
        'customer_email',
        'customer_name',
        'shipping_address',
        'subtotal_cents',
        'shipping_cents',
        'total_cents',
        'currency',
        'payment_provider',
        'payment_reference',
        'paid_at',
        'shipped_at',
        'notes',
    ];

    protected $casts = [
        'shipping_address' => 'array',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'subtotal_cents' => 'integer',
        'shipping_cents' => 'integer',
        'total_cents' => 'integer',
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

            if (empty($model->order_number)) {
                $model->order_number = self::generateOrderNumber();
            }
        });
    }

    public static function generateOrderNumber(): string
    {
        $prefix = config('syltjunkie.shop.order_number_prefix', 'SJ');
        $date = now()->format('Ymd');

        $latest = DB::table('sj_shop_orders')
            ->where('order_number', 'like', "{$prefix}-{$date}-%")
            ->orderByDesc('order_number')
            ->value('order_number');

        if ($latest) {
            $lastSeq = (int) substr($latest, -3);
            $seq = $lastSeq + 1;
        } else {
            $seq = 1;
        }

        return sprintf('%s-%s-%03d', $prefix, $date, $seq);
    }

    // ── Relationships ──────────────────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(SjShopOrderItem::class, 'order_id');
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    // ── Accessors ──────────────────────────────────────────

    public function getFormattedTotalAttribute(): string
    {
        return number_format($this->total_cents / 100, 2, ',', '.') . ' €';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'paid' => 'blue',
            'shipped' => 'indigo',
            'completed' => 'green',
            'cancelled' => 'red',
            'refunded' => 'gray',
            default => 'gray',
        };
    }
}
