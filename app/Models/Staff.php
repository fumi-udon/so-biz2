<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'staff';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'name',
        'pin_code',
        'role',
        'target_weekly_hours',
        'wage',
        'hourly_wage',
        'is_manager',
        'job_level_id',
        'age',
        'gender',
        'origin',
        'note',
        'fixed_shifts',
        'extra_profile',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fixed_shifts' => 'array',
            'extra_profile' => 'array',
            'is_active' => 'boolean',
            'is_manager' => 'boolean',
            'hourly_wage' => 'decimal:3',
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
     * @return BelongsTo<JobLevel, $this>
     */
    public function jobLevel(): BelongsTo
    {
        return $this->belongsTo(JobLevel::class);
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * @return HasMany<StaffAbsence, $this>
     */
    public function staffAbsences(): HasMany
    {
        return $this->hasMany(StaffAbsence::class);
    }

    /**
     * @return HasMany<RoutineTask, $this>
     */
    public function assignedRoutineTasks(): HasMany
    {
        return $this->hasMany(RoutineTask::class, 'assigned_staff_id');
    }

    /**
     * @return HasMany<InventoryItem, $this>
     */
    public function assignedInventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'assigned_staff_id');
    }

    /**
     * 給与計算用の基本給（スタブ）。
     * 固定給カラムが未確定のため、現状は wage を返す。
     */
    public function calculateMonthlyBaseSalary(int $year, int $month): int
    {
        return (int) ($this->wage ?? 0);
    }

    /**
     * 月間遅刻ペナルティ額を計算する。
     */
    public function calculateMonthlyPenalty(int $year, int $month): int
    {
        $lateMinutes = (int) Attendance::query()
            ->where('staff_id', $this->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->sum('late_minutes');

        $penaltyPerMinute = (int) config('payroll.late_penalty_per_minute', 10);

        return $lateMinutes * max($penaltyPerMinute, 0);
    }

    /**
     * 月間チップ分配総額を計算する。
     */
    public function calculateMonthlyTotalTips(int $year, int $month): float
    {
        return (float) StaffTip::query()
            ->where('staff_id', $this->id)
            ->whereHas('dailyTip', function ($query) use ($year, $month): void {
                $query->whereYear('business_date', $year)
                    ->whereMonth('business_date', $month);
            })
            ->sum('amount');
    }
}
