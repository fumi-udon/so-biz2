<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Guest / POS order batch (one submission). Not to be confused with {@see Order} (legacy client form).
 */
class PosOrder extends Model
{
    protected $table = 'orders';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'table_session_id',
        'status',
        'total_price_minor',
        'order_discount_minor',
        'rounding_adjustment_minor',
        'placed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_price_minor' => 'integer',
            'order_discount_minor' => 'integer',
            'rounding_adjustment_minor' => 'integer',
            'placed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return BelongsTo<TableSession, $this>
     */
    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class, 'order_id');
    }
}
