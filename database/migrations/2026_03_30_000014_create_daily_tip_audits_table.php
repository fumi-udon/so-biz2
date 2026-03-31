<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_tip_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->date('target_date')->nullable();
            $table->string('shift', 20)->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['target_date', 'shift'], 'daily_tip_audits_target_date_shift_index');
            $table->index(['action', 'created_at'], 'daily_tip_audits_action_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_tip_audits');
    }
};
