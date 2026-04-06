<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceEditLog extends Model
{
    protected $fillable = [
        'attendance_id',
        'target_staff_id',
        'editor_staff_id',
        'field_name',
        'old_value',
        'new_value',
        'ip_address',
    ];

    /** @return BelongsTo<Attendance, $this> */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /** @return BelongsTo<Staff, $this> */
    public function targetStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'target_staff_id');
    }

    /** @return BelongsTo<Staff, $this> */
    public function editorStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'editor_staff_id');
    }
}
