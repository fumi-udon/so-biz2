<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finances', function (Blueprint $table) {
            $table->string('close_meal', 16)
                ->default('dinner')
                ->after('business_date');
            $table->decimal('external_sales_lunch', 12, 3)
                ->default(0)
                ->after('montant_initial');
            $table->decimal('external_sales_dinner', 12, 3)
                ->default(0)
                ->after('external_sales_lunch');
        });
    }

    public function down(): void
    {
        Schema::table('finances', function (Blueprint $table) {
            $table->dropColumn([
                'close_meal',
                'external_sales_lunch',
                'external_sales_dinner',
            ]);
        });
    }
};
