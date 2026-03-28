<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineTaskLog extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'routine_task_id',
        'date',
        'completed_by_staff_id',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RoutineTask, $this>
     */
    public function routineTask(): BelongsTo
    {
        return $this->belongsTo(RoutineTask::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function completedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'completed_by_staff_id');
    }
}
