<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menu_items') || Schema::hasColumn('menu_items', 'role_category')) {
            return;
        }

        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('role_category', 32)->default('kitchen');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('menu_items') || ! Schema::hasColumn('menu_items', 'role_category')) {
            return;
        }

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('role_category');
        });
    }
};
