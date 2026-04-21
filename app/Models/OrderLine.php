<?php

namespace App\Models;

use App\Enums\OrderLineStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderLine extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'shop_id',
        'menu_item_id',
        'qty',
        'unit_price_minor',
        'line_total_minor',
        'line_discount_minor',
        'snapshot_name',
        'snapshot_kitchen_name',
        'snapshot_options_payload',
        'status',
        'line_revision',
        'kds_ticket_batch_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot_options_payload' => 'array',
            'status' => OrderLineStatus::class,
            'line_revision' => 'integer',
            'unit_price_minor' => 'integer',
            'line_total_minor' => 'integer',
            'line_discount_minor' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (OrderLine $line): void {
            if ($line->shop_id !== null || $line->order_id === null) {
                return;
            }
            $shopId = PosOrder::query()->whereKey($line->order_id)->value('shop_id');
            if ($shopId !== null) {
                $line->shop_id = (int) $shopId;
            }
        });
    }

    /**
     * @return BelongsTo<PosOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'order_id');
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
