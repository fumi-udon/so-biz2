<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('finances')) {
            return;
        }

        if (! Schema::hasColumn('finances', 'responsible_pin_verified')) {
            Schema::table('finances', function (Blueprint $table): void {
                $table->boolean('responsible_pin_verified')->default(false);
            });
        }

        if (! Schema::hasColumn('finances', 'panel_operator_user_id')) {
            Schema::table('finances', function (Blueprint $table): void {
                $table->foreignId('panel_operator_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        // 旧行: 当時は created_by のみ（＝パネル操作者）。責任者 PIN は未実施。
        if (Schema::hasColumn('finances', 'panel_operator_user_id') && Schema::hasColumn('finances', 'created_by')) {
            DB::table('finances')
                ->whereNull('panel_operator_user_id')
                ->whereNotNull('created_by')
                ->update(['panel_operator_user_id' => DB::raw('created_by')]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('finances')) {
            return;
        }

        if (Schema::hasColumn('finances', 'panel_operator_user_id')) {
            Schema::table('finances', function (Blueprint $table): void {
                $table->dropForeign(['panel_operator_user_id']);
            });
        }

        $drop = [];
        if (Schema::hasColumn('finances', 'responsible_pin_verified')) {
            $drop[] = 'responsible_pin_verified';
        }
        if (Schema::hasColumn('finances', 'panel_operator_user_id')) {
            $drop[] = 'panel_operator_user_id';
        }
        if ($drop !== []) {
            Schema::table('finances', function (Blueprint $table) use ($drop): void {
                $table->dropColumn($drop);
            });
        }
    }
};
