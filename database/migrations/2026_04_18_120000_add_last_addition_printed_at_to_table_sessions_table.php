<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_sessions', function (Blueprint $table) {
            $table->timestamp('last_addition_printed_at')->nullable()->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('table_sessions', function (Blueprint $table) {
            $table->dropColumn('last_addition_printed_at');
        });
    }
};
