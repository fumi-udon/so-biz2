<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    /**
     * Legacy client-order form buffer (pre–Phase 3). POS domain uses {@see PosOrder} on table `orders`.
     */
    protected $table = 'legacy_orders';

    protected $fillable = [
        'table_number',
        'items',
        'status',
    ];
}
