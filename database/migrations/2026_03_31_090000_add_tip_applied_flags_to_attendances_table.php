<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->boolean('is_lunch_tip_applied')
                ->default(false)
                ->after('is_tip_eligible');
            $table->boolean('is_dinner_tip_applied')
                ->default(false)
                ->after('is_lunch_tip_applied');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'is_lunch_tip_applied',
                'is_dinner_tip_applied',
            ]);
        });
    }
};
