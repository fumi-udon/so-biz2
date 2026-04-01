<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('finances')) {
            return;
        }

        if (! Schema::hasColumn('finances', 'responsible_staff_id')) {
            Schema::table('finances', function (Blueprint $table): void {
                $table->foreignId('responsible_staff_id')
                    ->nullable()
                    ->constrained('staff')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('finances') || ! Schema::hasColumn('finances', 'responsible_staff_id')) {
            return;
        }

        Schema::table('finances', function (Blueprint $table): void {
            $table->dropForeign(['responsible_staff_id']);
        });

        Schema::table('finances', function (Blueprint $table): void {
            $table->dropColumn('responsible_staff_id');
        });
    }
};
