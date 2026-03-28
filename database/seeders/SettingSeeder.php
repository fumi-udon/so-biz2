<?php

namespace Database\Seeders;

use App\Models\Setting;
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
    }
}
