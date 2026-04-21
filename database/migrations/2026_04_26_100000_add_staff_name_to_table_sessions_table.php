<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('table_sessions', 'staff_name')) {
                $table->string('staff_name')->nullable()->after('closed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('table_sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('table_sessions', 'staff_name')) {
                $table->dropColumn('staff_name');
            }
        });
    }
};
