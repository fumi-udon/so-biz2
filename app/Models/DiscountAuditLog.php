<?php

namespace App\Models;

use App\Domains\Pos\Discount\DiscountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountAuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'table_session_id',
        'order_id',
        'order_line_id',
        'discount_type',
        'basis_minor',
        'amount_minor',
        'percent_basis_points',
        'actor_user_id',
        'actor_job_level',
        'approver_staff_id',
        'reason',
        'idempotency_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'basis_minor' => 'integer',
            'amount_minor' => 'integer',
            'percent_basis_points' => 'integer',
            'actor_job_level' => 'integer',
            'created_at' => 'datetime',
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
     * @return BelongsTo<PosOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'order_id');
    }

    /**
     * @return BelongsTo<OrderLine, $this>
     */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class, 'order_line_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'approver_staff_id');
    }
}
