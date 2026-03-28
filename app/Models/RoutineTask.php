<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoutineTask extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'category',
        'name',
        'timing',
        'assigned_staff_id',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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
     * @return BelongsTo<Staff, $this>
     */
    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_staff_id');
    }

    /**
     * @return HasMany<RoutineTaskLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(RoutineTaskLog::class);
    }
}
