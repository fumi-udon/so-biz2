<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableSessionSettlement extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'table_session_id',
        'order_subtotal_minor',
        'order_discount_applied_minor',
        'total_before_rounding_minor',
        'rounding_adjustment_minor',
        'final_total_minor',
        'tendered_minor',
        'change_minor',
        'payment_method',
        'session_revision_at_settle',
        'settled_by_user_id',
        'settled_at',
        'print_bypassed',
        'bypass_reason',
        'bypassed_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_subtotal_minor' => 'integer',
            'order_discount_applied_minor' => 'integer',
            'total_before_rounding_minor' => 'integer',
            'rounding_adjustment_minor' => 'integer',
            'final_total_minor' => 'integer',
            'tendered_minor' => 'integer',
            'change_minor' => 'integer',
            'payment_method' => PaymentMethod::class,
            'session_revision_at_settle' => 'integer',
            'settled_at' => 'datetime',
            'print_bypassed' => 'boolean',
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
     * @return BelongsTo<User, $this>
     */
    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function bypassedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bypassed_by_user_id');
    }
}
