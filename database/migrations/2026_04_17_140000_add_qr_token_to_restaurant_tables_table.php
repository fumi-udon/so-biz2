<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->string('qr_token', 64)->nullable()->after('name');
            $table->unique(['shop_id', 'qr_token']);
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->dropUnique(['shop_id', 'qr_token']);
            $table->dropColumn('qr_token');
        });
    }
};
