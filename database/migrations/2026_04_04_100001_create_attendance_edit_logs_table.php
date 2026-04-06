<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_edit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendances')->cascadeOnDelete();
            $table->foreignId('target_staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('editor_staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('field_name', 64);
            $table->string('old_value', 20)->nullable();
            $table->string('new_value', 20)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['attendance_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_edit_logs');
    }
};
