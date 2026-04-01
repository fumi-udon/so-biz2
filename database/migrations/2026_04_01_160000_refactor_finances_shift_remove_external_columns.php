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

        if (! Schema::hasColumn('finances', 'shift')) {
            Schema::table('finances', function (Blueprint $table) {
                $table->string('shift', 32)->default('dinner')->after('business_date');
            });
        }

        if (Schema::hasColumn('finances', 'close_meal')) {
            $tableName = Schema::getConnection()->getTablePrefix().'finances';
            DB::statement("UPDATE `{$tableName}` SET `shift` = `close_meal`");
            Schema::table('finances', function (Blueprint $table) {
                $table->dropColumn('close_meal');
            });
        }

        $toDrop = collect([
            'external_sales_lunch',
            'external_sales_dinner',
            'external_sales',
            'external_api_has_error',
            'external_api_error_message',
        ])->filter(fn (string $c): bool => Schema::hasColumn('finances', $c))->values()->all();

        if ($toDrop !== []) {
            Schema::table('finances', function (Blueprint $table) use ($toDrop): void {
                $table->dropColumn($toDrop);
            });
        }
    }

    public function down(): void
    {
        // 手動でのロールバックを推奨（外部売上カラム復元はスキーマ差分に依存するため）
    }
};
