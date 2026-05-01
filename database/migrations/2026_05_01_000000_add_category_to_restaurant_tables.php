<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('restaurant_tables', 'category')) {
            Schema::table('restaurant_tables', function (Blueprint $table) {
                $table->string('category', 20)->nullable()->after('name');
            });
        }

        $tableName = Schema::getConnection()->getTablePrefix().'restaurant_tables';

        DB::statement("UPDATE `{$tableName}` SET category = CASE
    WHEN name LIKE 'TK%' THEN 'takeaway'
    WHEN name LIKE 'ST%' THEN 'staff'
    WHEN name LIKE 'T%' THEN 'customer'
    ELSE 'customer'
END");
    }

    public function down(): void
    {
        if (Schema::hasColumn('restaurant_tables', 'category')) {
            Schema::table('restaurant_tables', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }
};
