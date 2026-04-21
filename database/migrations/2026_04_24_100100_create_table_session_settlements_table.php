<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: immutable snapshot of a completed checkout (Cloture).
 *
 * One row per table_session. Stores:
 *  - the full PricingEngine result at the moment of settlement,
 *  - tendered/change amounts (for cash),
 *  - who settled it, how (payment_method) and whether a printer-bypass was used.
 *
 * The uniqueness on (table_session_id) combined with optimistic locking on
 * table_sessions.session_revision guarantees checkout cannot be run twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_session_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_session_id')->unique()->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('order_subtotal_minor');
            $table->unsignedBigInteger('order_discount_applied_minor')->default(0);
            $table->unsignedBigInteger('total_before_rounding_minor');
            $table->unsignedBigInteger('rounding_adjustment_minor')->default(0);
            $table->unsignedBigInteger('final_total_minor');

            $table->unsignedBigInteger('tendered_minor')->default(0);
            $table->unsignedBigInteger('change_minor')->default(0);

            $table->string('payment_method', 32);
            $table->unsignedBigInteger('session_revision_at_settle');

            $table->foreignId('settled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('settled_at');

            $table->boolean('print_bypassed')->default(false);
            $table->string('bypass_reason', 255)->nullable();
            $table->foreignId('bypassed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['shop_id', 'settled_at']);
            $table->index(['shop_id', 'payment_method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_session_settlements');
    }
};
