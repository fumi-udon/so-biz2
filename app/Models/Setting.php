<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected static array $cache = [];

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
        if (array_key_exists($key, static::$cache)) {
            return static::$cache[$key];
        }

        $row = static::query()->where('key', $key)->first();
        static::$cache[$key] = ($row !== null && $row->value !== null) ? $row->value : $default;

        return static::$cache[$key];
    }
}
