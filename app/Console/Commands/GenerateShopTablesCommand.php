<?php

namespace App\Console\Commands;

use App\Models\RestaurantTable;
use App\Models\Shop;
use Database\Seeders\ShopTableSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateShopTablesCommand extends Command
{
    // コマンド名と引数（店舗ID、店舗短縮コード）
    protected $signature = 'app:generate-shop-tables {shop_id} {shop_short_code}';

    protected $description = '指定店舗の restaurant_tables を削除のうえ再生成（Seeder と同一仕様の強制リセット用）';

    public function handle(): int
    {
        $shopId = (int) $this->argument('shop_id');
        $shortCode = $this->argument('shop_short_code');

        if (! Shop::where('id', $shopId)->exists()) {
            $this->error("エラー: Shop ID {$shopId} が見つかりません。");

            return self::FAILURE;
        }

        $this->info("Shop ID {$shopId} のテーブルを再構築します...");

        try {
            DB::transaction(function () use ($shopId, $shortCode) {
                RestaurantTable::where('shop_id', $shopId)->delete();
                ShopTableSeeder::insertDefaultTablesForShop($shopId, $shortCode);
            });

            $this->info('✨ テーブルの生成が完了しました！ (Client: T01〜T99, Staff: ST01〜ST10, Takeout: TK01〜TK20)');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('テーブルの生成中にエラーが発生しました: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
