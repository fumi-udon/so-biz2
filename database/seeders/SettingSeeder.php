<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\InventorySettingOptions;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'late_tolerance_minutes'],
            [
                'value' => 10,
                'description' => 'Pointage : minutes de tolérance après l’heure prévue (retard).',
            ],
        );

        Setting::query()->updateOrCreate(
            ['key' => 'staff_roles'],
            [
                'value' => ['hall', 'kitchen', 'manager', 'support'],
                'description' => 'Liste des rôles proposés pour les employés (JSON array).',
            ],
        );

        Setting::query()->updateOrCreate(
            ['key' => InventorySettingOptions::KEY_TIMING],
            [
                'value' => ['close', 'open', 'lunch', 'prep', 'night_close'],
                'description' => '棚卸し・ルーティンで使う「確認タイミング」の選択肢（タグ）。例: close=閉店前',
            ],
        );

        Setting::query()->updateOrCreate(
            ['key' => InventorySettingOptions::KEY_CATEGORY],
            [
                'value' => [
                    '刺身・鮮魚',
                    '野菜・香草',
                    '米・麺',
                    '調味料',
                    'だし・出汁',
                    '油脂',
                    '冷凍食品',
                    '酒類',
                    '飲料',
                    '乾物',
                    '半製品',
                    '衛生・包装',
                ],
                'description' => '棚卸し品目の「カテゴリ」候補（タグ）。',
            ],
        );

        Setting::query()->updateOrCreate(
            ['key' => InventorySettingOptions::KEY_UNIT],
            [
                'value' => ['kg', 'g', '本', '尾', '枚', 'ℓ', 'mL', '箱', 'パック', '個', '束', '杯'],
                'description' => '棚卸し品目の「単位」候補（タグ）。',
            ],
        );
    }
}
