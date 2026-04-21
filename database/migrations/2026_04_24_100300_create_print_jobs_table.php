<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: idempotent print job queue.
 *
 * idempotency_key = sha256(table_session_id ':' session_revision ':' intent)
 * Enforced UNIQUE so the same logical print (e.g. Addition at revision=7) can
 * never be scheduled twice even under race conditions. Retries are tracked by
 * attempt_count; bypass is a first-class terminal state so the cashier can
 * close the session even when the printer is hardware-offline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->string('intent', 32);
            $table->string('idempotency_key', 128)->unique();

            $table->longText('payload_xml');
            $table->json('payload_meta')->nullable();

            $table->string('status', 16)->default('pending');

            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('last_error_code', 64)->nullable();
            $table->string('last_error_message', 500)->nullable();

            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamp('bypassed_at')->nullable();
            $table->foreignId('bypassed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('bypass_reason', 255)->nullable();

            $table->timestamps();

            $table->index(['shop_id', 'status']);
            $table->index(['table_session_id', 'intent']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
