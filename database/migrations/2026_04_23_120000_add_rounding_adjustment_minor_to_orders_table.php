<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'rounding_adjustment_minor')) {
                $table->unsignedBigInteger('rounding_adjustment_minor')
                    ->default(0)
                    ->after('total_price_minor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'rounding_adjustment_minor')) {
                $table->dropColumn('rounding_adjustment_minor');
            }
        });
    }
};
