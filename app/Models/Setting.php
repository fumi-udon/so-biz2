<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $cacheKey = 'setting_cache_'.$key;

        if (app()->has($cacheKey)) {
            return app()->make($cacheKey);
        }

        $row = static::query()->where('key', $key)->first();
        $val = ($row !== null && $row->value !== null) ? $row->value : $default;

        app()->instance($cacheKey, $val);

        return $val;
    }
}
