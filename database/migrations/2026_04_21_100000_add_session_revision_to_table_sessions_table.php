<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('session_revision')->default(0)->after('last_addition_printed_at');
        });
    }

    public function down(): void
    {
        Schema::table('table_sessions', function (Blueprint $table) {
            $table->dropColumn('session_revision');
        });
    }
};
