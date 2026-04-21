<?php

namespace App\Models;

use App\Enums\TableSessionStatus;
use App\Support\Pos\StaffTableSettlementPricing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TableSession extends Model
{
    protected static function booted(): void
    {
        static::saving(function (TableSession $s): void {
            if (! StaffTableSettlementPricing::isStaffMealTableId((int) $s->restaurant_table_id)) {
                $s->staff_name = null;
            }
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'restaurant_table_id',
        'token',
        'status',
        'opened_at',
        'closed_at',
        'staff_name',
        'last_addition_printed_at',
        'session_revision',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TableSessionStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_addition_printed_at' => 'datetime',
            'session_revision' => 'integer',
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
     * @return BelongsTo<RestaurantTable, $this>
     */
    public function restaurantTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class);
    }

    /**
     * @return HasMany<PosOrder, $this>
     */
    public function posOrders(): HasMany
    {
        return $this->hasMany(PosOrder::class, 'table_session_id');
    }
}
