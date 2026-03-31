<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyTipDistribution extends Model
{
    protected $fillable = [
        'daily_tip_id',
        'staff_id',
        'weight',
        'amount',
        'is_tardy_deprived',
        'is_manual_added',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:3',
            'amount' => 'decimal:3',
            'is_tardy_deprived' => 'boolean',
            'is_manual_added' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<DailyTip, $this>
     */
    public function dailyTip(): BelongsTo
    {
        return $this->belongsTo(DailyTip::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
