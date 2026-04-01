<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_records')) {
            return;
        }

        Schema::create('inventory_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('quantity', 8, 2);
            $table->foreignId('recorded_by_staff_id')->constrained('staff')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['inventory_item_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_records');
    }
};
