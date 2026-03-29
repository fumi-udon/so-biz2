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
            $table->index(['date', 'staff_id'], 'attendances_date_staff_id_index');
        });

        Schema::table('inventory_records', function (Blueprint $table) {
            $table->index(['date', 'inventory_item_id'], 'inventory_records_date_item_id_index');
        });

        Schema::table('routine_task_logs', function (Blueprint $table) {
            $table->index(['date', 'routine_task_id'], 'routine_task_logs_date_task_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendances_date_staff_id_index');
        });

        Schema::table('inventory_records', function (Blueprint $table) {
            $table->dropIndex('inventory_records_date_item_id_index');
        });

        Schema::table('routine_task_logs', function (Blueprint $table) {
            $table->dropIndex('routine_task_logs_date_task_id_index');
        });
    }
};
