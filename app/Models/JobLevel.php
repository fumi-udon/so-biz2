<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobLevel extends Model
{
    protected $fillable = [
        'level',
        'name',
        'default_weight',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'default_weight' => 'decimal:3',
        ];
    }

    /**
     * @return HasMany<Staff, $this>
     */
    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }
}
