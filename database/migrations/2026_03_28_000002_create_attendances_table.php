<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->date('date');
            $table->string('shift_type')->nullable();
            $table->string('status')->nullable();
            $table->dateTime('scheduled_in_at')->nullable();
            $table->dateTime('lunch_in_at')->nullable();
            $table->dateTime('lunch_out_at')->nullable();
            $table->dateTime('dinner_in_at')->nullable();
            $table->dateTime('dinner_out_at')->nullable();
            $table->integer('late_minutes')->default(0);
            $table->boolean('is_tip_eligible')->default(false);
            $table->boolean('is_edited_by_admin')->default(false);
            $table->text('admin_note')->nullable();
            $table->text('in_note')->nullable();
            $table->text('out_note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
