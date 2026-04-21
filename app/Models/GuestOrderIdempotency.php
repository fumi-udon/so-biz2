<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestOrderIdempotency extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'table_session_id',
        'idempotency_key',
        'pos_order_id',
    ];

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
    public function posOrder(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }
}
