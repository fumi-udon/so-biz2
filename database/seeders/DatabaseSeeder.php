<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(SettingSeeder::class);
        $this->call(CloseTaskSeeder::class);
        $this->call(DietaryBadgeSystemSeeder::class);
        $this->call(RestaurantTableDashboardSeeder::class);
        $this->call(ShopTableSeeder::class);
        // Dev: wipe + menu (tapas/ramen/drink, ~20 items) — ./vendor/bin/sail artisan db:seed --class=MenuCatalogTestDataSeeder
    }
}
