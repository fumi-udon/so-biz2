<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Finance extends Model
{
    protected $fillable = [
        'business_date',
        'shift',
        'recettes',
        'cash',
        'cheque',
        'carte',
        'chips',
        'montant_initial',
        'register_total',
        'system_calculated_tip',
        'final_difference',
        'tolerance_used',
        'verdict',
        'close_status',
        'system_tip_amount',
        'declared_tip_amount',
        'final_tip_amount',
        'reserve_amount',
        'failure_reason',
        'close_snapshot',
        'responsible_pin_verified',
        'panel_operator_user_id',
        'responsible_staff_id',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'recettes' => 'decimal:3',
            'cash' => 'decimal:3',
            'cheque' => 'decimal:3',
            'carte' => 'decimal:3',
            'chips' => 'decimal:3',
            'montant_initial' => 'decimal:3',
            'register_total' => 'decimal:3',
            'system_calculated_tip' => 'decimal:3',
            'final_difference' => 'decimal:3',
            'tolerance_used' => 'decimal:3',
            'system_tip_amount' => 'decimal:3',
            'declared_tip_amount' => 'decimal:3',
            'final_tip_amount' => 'decimal:3',
            'reserve_amount' => 'decimal:3',
            'close_snapshot' => 'array',
            'responsible_pin_verified' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function panelOperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'panel_operator_user_id');
    }

    public function responsibleStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_staff_id');
    }
}
