<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 128);
            $table->json('payload');
            $table->string('status', 32)->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'outbox_events_status_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
