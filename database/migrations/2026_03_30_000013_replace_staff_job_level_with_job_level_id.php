<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            $table->dropColumn('job_level');
            $table->foreignId('job_level_id')
                ->nullable()
                ->after('is_manager')
                ->constrained('job_levels')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('job_level_id');
            $table->string('job_level')->nullable()->after('is_manager');
        });
    }
};
