<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Transactional Outbox (technical_contract_v4.md §7 / §9).
 *
 * @property int $id
 * @property string $event_type
 * @property array<string, mixed> $payload
 * @property string $status
 * @property Carbon|null $processed_at
 */
class OutboxEvent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_type',
        'payload',
        'status',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
