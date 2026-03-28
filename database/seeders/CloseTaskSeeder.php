<?php

namespace Database\Seeders;

use App\Models\CloseTask;
use Illuminate\Database\Seeder;

class CloseTaskSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'title' => 'レジのお金確認',
                'description' => '釣り銭・売上とレジ残高を照合し、差異がないか確認してください。',
            ],
            [
                'title' => 'ガスの元栓',
                'description' => '厨房・給湯など、使用したガス機器の元栓を閉めましたか。',
            ],
            [
                'title' => '戸締り',
                'description' => '出入口・裏口・窓の施錠とシャッターを確認してください。',
            ],
            [
                'title' => '冷蔵・冷凍庫の温度',
                'description' => '設定温度が適正か、扉が確実に閉まっているか確認してください。',
            ],
            [
                'title' => 'ゴミ出し・清掃',
                'description' => '店内の簡易清掃と、ゴミの分別・保管場所の確認を行ってください。',
            ],
        ];

        foreach ($rows as $row) {
            CloseTask::query()->updateOrCreate(
                ['title' => $row['title']],
                [
                    'description' => $row['description'],
                    'image_path' => null,
                    'is_active' => true,
                ],
            );
        }
    }
}
