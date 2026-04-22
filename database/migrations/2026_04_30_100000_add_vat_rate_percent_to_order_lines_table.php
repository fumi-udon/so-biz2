<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 注文行に税率（%）をスナップショット。会計上、印字・税集計は主に本列＋TTC 明細を使用する。
 * 移行時点の env（TVA_TN / POS_RECEIPT_VAT_DEFAULT）で既存行を一括補完する（完全な歴史再現ではない）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_lines')) {
            return;
        }
        if (! Schema::hasColumn('order_lines', 'vat_rate_percent')) {
            Schema::table('order_lines', function (Blueprint $table): void {
                if (Schema::hasColumn('order_lines', 'line_discount_minor')) {
                    $table->decimal('vat_rate_percent', 5, 2)->nullable()->after('line_discount_minor');
                } else {
                    $table->decimal('vat_rate_percent', 5, 2)->nullable();
                }
            });
        }

        $rate = (float) env('TVA_TN', env('POS_RECEIPT_VAT_DEFAULT', 19));
        DB::table('order_lines')->whereNull('vat_rate_percent')->update(['vat_rate_percent' => $rate]);
    }

    public function down(): void
    {
        if (Schema::hasTable('order_lines') && Schema::hasColumn('order_lines', 'vat_rate_percent')) {
            Schema::table('order_lines', function (Blueprint $table): void {
                $table->dropColumn('vat_rate_percent');
            });
        }
    }
};
