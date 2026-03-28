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
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('name');
            $table->string('pin_code')->nullable();
            $table->string('role')->nullable();
            $table->integer('target_weekly_hours')->nullable();
            $table->decimal('wage', 8, 2)->nullable();
            $table->string('job_level')->nullable();
            $table->integer('age')->nullable();
            $table->string('gender')->nullable();
            $table->string('origin')->nullable();
            $table->text('note')->nullable();
            $table->json('fixed_shifts')->nullable();
            $table->json('extra_profile')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
