<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 本番などで `2026_04_17_120000_menu_catalog_and_shop_slug` 実行時点で
 * `menu_items` が既に存在していた場合、create ブロックがスキップされ
 * `menu_category_id` が欠落することがある。その穴埋め用（既存マイグレは変更しない）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menu_items') || Schema::hasColumn('menu_items', 'menu_category_id')) {
            return;
        }

        if (! Schema::hasTable('menu_categories')) {
            return;
        }

        Schema::table('menu_items', function (Blueprint $table): void {
            // 既存行がある環境向けに一旦 nullable。バックフィル後に NOT NULL 化する場合は別マイグレで行う。
            $table->foreignId('menu_category_id')
                ->nullable()
                ->after('shop_id')
                ->constrained('menu_categories')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('menu_items') || ! Schema::hasColumn('menu_items', 'menu_category_id')) {
            return;
        }

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropForeign(['menu_category_id']);
            $table->dropColumn('menu_category_id');
        });
    }
};
