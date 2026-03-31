<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyTip extends Model
{
    protected $fillable = [
        'business_date',
        'shift',
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'total_amount' => 'decimal:3',
        ];
    }

    /**
     * @return HasMany<DailyTipDistribution, $this>
     */
    public function distributions(): HasMany
    {
        return $this->hasMany(DailyTipDistribution::class);
    }
}
