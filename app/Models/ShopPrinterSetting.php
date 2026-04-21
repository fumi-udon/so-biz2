<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopPrinterSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'printer_ip',
        'printer_port',
        'device_id',
        'crypto',
        'timeout_ms',
    ];

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    protected function casts(): array
    {
        return [
            'crypto' => 'boolean',
            'timeout_ms' => 'integer',
        ];
    }
}
