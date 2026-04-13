<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->boolean('is_lunch_auto_clocked_out')
                ->default(false)
                ->after('is_edited_by_admin');
            $table->boolean('is_dinner_auto_clocked_out')
                ->default(false)
                ->after('is_lunch_auto_clocked_out');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropColumn(['is_lunch_auto_clocked_out', 'is_dinner_auto_clocked_out']);
        });
    }
};
