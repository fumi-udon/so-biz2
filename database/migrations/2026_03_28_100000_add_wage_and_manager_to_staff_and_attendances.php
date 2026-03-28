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
        Schema::table('staff', function (Blueprint $table) {
            $table->integer('hourly_wage')->nullable()->after('wage')->comment('時給');
            $table->boolean('is_manager')->default(false)->after('is_active')->comment('マネージャー権限');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('approved_by_manager_id')
                ->nullable()
                ->after('staff_id')
                ->constrained('staff')
                ->comment('出勤編集を承認したマネージャー');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_manager_id');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['hourly_wage', 'is_manager']);
        });
    }
};
