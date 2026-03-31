<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_tip_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_tip_id')->constrained('daily_tips')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->decimal('weight', 8, 3)->default(0);
            $table->decimal('amount', 10, 3)->default(0);
            $table->boolean('is_tardy_deprived')->default(false);
            $table->boolean('is_manual_added')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['daily_tip_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_tip_distributions');
    }
};
