<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: append-only audit trail for every discount applied on a POS session.
 *
 * "Who authorized what discount, on which table, why, verified by which PIN."
 * This table is the accountability backbone. Rows are never updated or deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();

            $table->foreignId('table_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_line_id')->nullable()->constrained('order_lines')->nullOnDelete();

            $table->string('discount_type', 16);

            $table->unsignedBigInteger('basis_minor');
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedInteger('percent_basis_points')->nullable();

            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('actor_job_level');
            $table->foreignId('approver_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->string('reason', 255);

            $table->string('idempotency_key', 128)->unique();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['shop_id', 'created_at']);
            $table->index(['table_session_id', 'discount_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_audit_logs');
    }
};
