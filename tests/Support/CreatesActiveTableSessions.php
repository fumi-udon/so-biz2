<?php

namespace Tests\Support;

use App\Enums\TableSessionStatus;
use App\Models\RestaurantTable;
use App\Models\Shop;
use App\Models\TableSession;
use Illuminate\Support\Str;

trait CreatesActiveTableSessions
{
    protected function createActiveTableSession(Shop $shop, RestaurantTable $table): TableSession
    {
        return TableSession::query()->create([
            'shop_id' => $shop->id,
            'restaurant_table_id' => $table->id,
            'token' => Str::lower(Str::random(48)),
            'status' => TableSessionStatus::Active,
            'opened_at' => now(),
            'closed_at' => null,
        ]);
    }
}
