<?php

namespace App\Models;

use App\Enums\TableSessionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RestaurantTable extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'shop_id',
        'name',
        'qr_token',
        'category',
        'sort_order',
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
     * @return HasMany<TableSession, $this>
     */
    public function tableSessions(): HasMany
    {
        return $this->hasMany(TableSession::class);
    }

    /**
     * Latest active session for this table (highest id among status = active).
     *
     * @return HasOne<TableSession, $this>
     */
    public function activeSession(): HasOne
    {
        return $this->hasOne(TableSession::class)
            ->ofMany(
                ['id' => 'max'],
                fn (Builder $query) => $query->where('status', TableSessionStatus::Active),
            );
    }
}
