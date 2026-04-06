<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAbsence extends Model
{
    public const MEAL_LUNCH = 'lunch';

    public const MEAL_DINNER = 'dinner';

    public const MEAL_FULL = 'full';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'staff_id',
        'date',
        'meal_type',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @return list<string>
     */
    public static function mealTypeOptions(): array
    {
        return [
            self::MEAL_LUNCH,
            self::MEAL_DINNER,
            self::MEAL_FULL,
        ];
    }
}
