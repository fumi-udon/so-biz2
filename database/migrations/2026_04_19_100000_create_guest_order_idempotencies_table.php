<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Orphan table from a previously failed run (e.g. index name too long) leaves MySQL
        // without a migrations row; drop so this migration can complete cleanly.
        Schema::dropIfExists('guest_order_idempotencies');

        Schema::create('guest_order_idempotencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_session_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key', 128);
            $table->foreignId('pos_order_id')->constrained('orders')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['table_session_id', 'idempotency_key'], 'idx_session_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_order_idempotencies');
    }
};
