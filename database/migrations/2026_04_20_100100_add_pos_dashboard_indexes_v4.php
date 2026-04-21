<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('table_sessions') && ! Schema::hasIndex('table_sessions', 'ts_shop_table_status_idx')) {
            Schema::table('table_sessions', function (Blueprint $table) {
                $table->index(['shop_id', 'restaurant_table_id', 'status'], 'ts_shop_table_status_idx');
            });
        }

        if (Schema::hasTable('table_sessions') && ! Schema::hasIndex('table_sessions', 'ts_table_status_id_idx')) {
            Schema::table('table_sessions', function (Blueprint $table) {
                $table->index(['restaurant_table_id', 'status', 'id'], 'ts_table_status_id_idx');
            });
        }

        if (Schema::hasTable('order_lines') && ! Schema::hasIndex('order_lines', 'ol_order_status_idx')) {
            Schema::table('order_lines', function (Blueprint $table) {
                $table->index(['order_id', 'status'], 'ol_order_status_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('table_sessions') && Schema::hasIndex('table_sessions', 'ts_shop_table_status_idx')) {
            Schema::table('table_sessions', function (Blueprint $table) {
                $table->dropIndex('ts_shop_table_status_idx');
            });
        }
        if (Schema::hasTable('table_sessions') && Schema::hasIndex('table_sessions', 'ts_table_status_id_idx')) {
            Schema::table('table_sessions', function (Blueprint $table) {
                $table->dropIndex('ts_table_status_id_idx');
            });
        }
        if (Schema::hasTable('order_lines') && Schema::hasIndex('order_lines', 'ol_order_status_idx')) {
            Schema::table('order_lines', function (Blueprint $table) {
                $table->dropIndex('ol_order_status_idx');
            });
        }
    }
};
