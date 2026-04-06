<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'staff_id',
        'approved_by_manager_id',
        'date',
        'shift_type',
        'status',
        'scheduled_in_at',
        'scheduled_dinner_at',
        'lunch_in_at',
        'lunch_out_at',
        'dinner_in_at',
        'dinner_out_at',
        'late_minutes',
        'is_tip_eligible',
        'is_lunch_tip_applied',
        'is_lunch_tip_denied',
        'is_dinner_tip_applied',
        'is_dinner_tip_denied',
        'is_edited_by_admin',
        'admin_note',
        'in_note',
        'out_note',
        'tip_weight_override',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'scheduled_in_at' => 'datetime',
            'scheduled_dinner_at' => 'datetime',
            'lunch_in_at' => 'datetime',
            'lunch_out_at' => 'datetime',
            'dinner_in_at' => 'datetime',
            'dinner_out_at' => 'datetime',
            'is_tip_eligible' => 'boolean',
            'is_lunch_tip_applied' => 'boolean',
            'is_lunch_tip_denied' => 'boolean',
            'is_dinner_tip_applied' => 'boolean',
            'is_dinner_tip_denied' => 'boolean',
            'is_edited_by_admin' => 'boolean',
            'tip_weight_override' => 'integer',
        ];
    }

    /**
     * 1日の合計労働時間（分）。ランチ・ディナー2区間のうち、出退勤が揃っている区間のみ合算。
     */
    public function calculateTotalMinutes(): ?int
    {
        return $this->workMinutes();
    }

    /**
     * ランチ・ディナー2区間の合計勤務時間（分）。打刻が揃っていない区間は無視。
     */
    public function workMinutes(): ?int
    {
        $total = 0;

        if ($this->lunch_in_at && $this->lunch_out_at) {
            $m = $this->lunch_in_at->diffInMinutes($this->lunch_out_at, false);
            if ($m > 0) {
                $total += $m;
            }
        }

        if ($this->dinner_in_at && $this->dinner_out_at) {
            $m = $this->dinner_in_at->diffInMinutes($this->dinner_out_at, false);
            if ($m > 0) {
                $total += $m;
            }
        }

        return $total > 0 ? $total : null;
    }

    /**
     * 給与計算用：時間（小数・2桁）。例: 7.50
     */
    public function workHoursDecimal(): ?float
    {
        $m = $this->workMinutes();

        if ($m === null) {
            return null;
        }

        return round($m / 60, 2);
    }

    /**
     * 表示用（例: 7.50 h）
     */
    public function formatWorkDuration(): ?string
    {
        $m = $this->workMinutes();

        if ($m === null) {
            return null;
        }

        $h = intdiv($m, 60);
        $min = $m % 60;

        return sprintf('%d:%02d', $h, $min);
    }

    /**
     * その日に遅刻が記録されているか（1行につき1回までカウント）
     */
    public function hasLateOccurrence(): bool
    {
        return ($this->late_minutes ?? 0) > 0;
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function approvedByManager(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'approved_by_manager_id');
    }

    public function hasMissingClockOut(): bool
    {
        return ($this->lunch_in_at !== null && $this->lunch_out_at === null)
            || ($this->dinner_in_at !== null && $this->dinner_out_at === null);
    }
}
