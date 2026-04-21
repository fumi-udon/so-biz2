<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('menu_items') && ! Schema::hasColumn('menu_items', 'kitchen_name')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->string('kitchen_name', 255)->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('menu_items') && Schema::hasColumn('menu_items', 'kitchen_name')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->dropColumn('kitchen_name');
            });
        }
    }
};
