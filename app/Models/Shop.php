<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shop extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Shop $shop): void {
            if ($shop->slug === '') {
                $shop->slug = null;
            }
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];

    /**
     * @return HasMany<Staff, $this>
     */
    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    /**
     * @return HasMany<RoutineTask, $this>
     */
    public function routineTasks(): HasMany
    {
        return $this->hasMany(RoutineTask::class);
    }

    /**
     * @return HasMany<InventoryItem, $this>
     */
    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    /**
     * @return HasMany<MenuCategory, $this>
     */
    public function menuCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class);
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    /**
     * @return HasMany<DietaryBadge, $this>
     */
    public function dietaryBadges(): HasMany
    {
        return $this->hasMany(DietaryBadge::class);
    }

    /**
     * @return HasMany<RestaurantTable, $this>
     */
    public function restaurantTables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class);
    }

    /**
     * @return HasMany<TableSession, $this>
     */
    public function tableSessions(): HasMany
    {
        return $this->hasMany(TableSession::class);
    }

    /**
     * @return HasMany<PosOrder, $this>
     */
    public function posOrders(): HasMany
    {
        return $this->hasMany(PosOrder::class);
    }

    /**
     * @return HasOne<ShopPrinterSetting, $this>
     */
    public function printerSetting(): HasOne
    {
        return $this->hasOne(ShopPrinterSetting::class);
    }
}
