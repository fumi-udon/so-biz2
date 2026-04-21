<?php

namespace App\Models;

use App\Enums\PrintIntent;
use App\Enums\PrintJobStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintJob extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'table_session_id',
        'order_id',
        'intent',
        'idempotency_key',
        'payload_xml',
        'payload_meta',
        'status',
        'attempt_count',
        'last_error_code',
        'last_error_message',
        'dispatched_at',
        'completed_at',
        'bypassed_at',
        'bypassed_by_user_id',
        'bypass_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'intent' => PrintIntent::class,
            'status' => PrintJobStatus::class,
            'payload_meta' => 'array',
            'attempt_count' => 'integer',
            'dispatched_at' => 'datetime',
            'completed_at' => 'datetime',
            'bypassed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function bypassedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bypassed_by_user_id');
    }
}
