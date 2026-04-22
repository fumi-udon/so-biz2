<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('table_sessions', 'customer_name')) {
                $table->string('customer_name')->nullable()->after('staff_name');
            }
            if (! Schema::hasColumn('table_sessions', 'customer_phone')) {
                $table->string('customer_phone')->nullable()->after('customer_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('table_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('table_sessions', 'customer_phone')) {
                $table->dropColumn('customer_phone');
            }
            if (Schema::hasColumn('table_sessions', 'customer_name')) {
                $table->dropColumn('customer_name');
            }
        });
    }
};
