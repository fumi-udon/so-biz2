<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyTipAudit extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'target_date',
        'shift',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'details' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
