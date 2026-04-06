<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->boolean('is_lunch_tip_denied')
                ->default(false)
                ->after('is_lunch_tip_applied');

            $table->boolean('is_dinner_tip_denied')
                ->default(false)
                ->after('is_dinner_tip_applied');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['is_lunch_tip_denied', 'is_dinner_tip_denied']);
        });
    }
};
