<?php

namespace Database\Seeders;

use App\Models\DietaryBadge;
use Illuminate\Database\Seeder;

class DietaryBadgeSystemSeeder extends Seeder
{
    /**
     * 全店共通の食事バッジ（アイコンは後から Filament でアップロード）。
     */
    public function run(): void
    {
        $rows = [
            ['slug' => 'vegetarian', 'name' => 'Végétarien', 'sort_order' => 10],
            ['slug' => 'vegan', 'name' => 'Vegan', 'sort_order' => 20],
            ['slug' => 'gluten-free', 'name' => 'Sans gluten', 'sort_order' => 30],
        ];

        foreach ($rows as $row) {
            DietaryBadge::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'shop_id' => null,
                    'name' => $row['name'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => true,
                    'icon_disk' => 'public',
                    'icon_path' => null,
                ],
            );
        }
    }
}
