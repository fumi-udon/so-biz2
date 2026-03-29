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
        'job_level',
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
            'hourly_wage' => 'integer',
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
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
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
}
