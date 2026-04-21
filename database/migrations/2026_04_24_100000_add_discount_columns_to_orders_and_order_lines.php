<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: persist applied discounts inline so FinalizeTableSettlementAction can replay
 * PricingEngine deterministically from raw row data. Audit history lives in
 * discount_audit_logs; these columns are the aggregated effective discount actually
 * applied to the monetary lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_lines', 'line_discount_minor')) {
                $table->unsignedBigInteger('line_discount_minor')
                    ->default(0)
                    ->after('line_total_minor');
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'order_discount_minor')) {
                $table->unsignedBigInteger('order_discount_minor')
                    ->default(0)
                    ->after('total_price_minor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'order_discount_minor')) {
                $table->dropColumn('order_discount_minor');
            }
        });

        Schema::table('order_lines', function (Blueprint $table): void {
            if (Schema::hasColumn('order_lines', 'line_discount_minor')) {
                $table->dropColumn('line_discount_minor');
            }
        });
    }
};
