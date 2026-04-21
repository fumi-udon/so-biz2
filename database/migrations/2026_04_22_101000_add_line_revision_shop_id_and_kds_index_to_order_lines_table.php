<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_lines', 'line_revision')) {
            Schema::table('order_lines', function (Blueprint $table) {
                $table->unsignedInteger('line_revision')->default(1)->after('status');
            });
        }

        if (! Schema::hasColumn('order_lines', 'shop_id')) {
            Schema::table('order_lines', function (Blueprint $table) {
                $table->unsignedBigInteger('shop_id')->nullable()->after('order_id');
            });
        }

        $rows = DB::table('order_lines')
            ->join('orders', 'orders.id', '=', 'order_lines.order_id')
            ->whereNull('order_lines.shop_id')
            ->select('order_lines.id', 'orders.shop_id')
            ->get();

        foreach ($rows as $row) {
            DB::table('order_lines')->where('id', $row->id)->update(['shop_id' => $row->shop_id]);
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $p = Schema::getConnection()->getTablePrefix();
            DB::statement("ALTER TABLE {$p}order_lines MODIFY shop_id BIGINT UNSIGNED NOT NULL");
        }

        if (! $this->orderLinesHasForeignKey('shop_id')) {
            Schema::table('order_lines', function (Blueprint $table) {
                $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            });
        }

        if (! $this->orderLinesHasIndex('ol_kds_pull_idx')) {
            Schema::table('order_lines', function (Blueprint $table) {
                $table->index(['shop_id', 'status', 'updated_at', 'id'], 'ol_kds_pull_idx');
            });
        }
    }

    public function down(): void
    {
        if ($this->orderLinesHasIndex('ol_kds_pull_idx')) {
            Schema::table('order_lines', function (Blueprint $table) {
                $table->dropIndex('ol_kds_pull_idx');
            });
        }

        if ($this->orderLinesHasForeignKey('shop_id')) {
            Schema::table('order_lines', function (Blueprint $table) {
                $table->dropForeign(['shop_id']);
            });
        }

        Schema::table('order_lines', function (Blueprint $table) {
            if (Schema::hasColumn('order_lines', 'shop_id')) {
                $table->dropColumn('shop_id');
            }
            if (Schema::hasColumn('order_lines', 'line_revision')) {
                $table->dropColumn('line_revision');
            }
        });
    }

    private function orderLinesHasForeignKey(string $column): bool
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return false;
        }
        $db = Schema::getConnection()->getDatabaseName();
        $table = Schema::getConnection()->getTablePrefix().'order_lines';
        $n = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$db, $table, $column]
        );

        return isset($n->c) && (int) $n->c > 0;
    }

    private function orderLinesHasIndex(string $indexName): bool
    {
        $connection = Schema::getConnection();
        $table = $connection->getTablePrefix().'order_lines';
        $indexes = $connection->getSchemaBuilder()->getIndexes($table);

        foreach ($indexes as $index) {
            if ($index['name'] === $indexName) {
                return true;
            }
        }

        return false;
    }
};
