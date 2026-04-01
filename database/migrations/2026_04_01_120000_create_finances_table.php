<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finances', function (Blueprint $table) {
            $table->id();
            $table->date('business_date');
            $table->decimal('recettes_soir', 12, 3)->default(0);
            $table->decimal('cash', 12, 3)->default(0);
            $table->decimal('cheque', 12, 3)->default(0);
            $table->decimal('carte', 12, 3)->default(0);
            $table->decimal('chips', 12, 3)->default(0);
            $table->decimal('montant_initial', 12, 3)->default(0);
            $table->decimal('external_sales', 12, 3)->default(0);
            $table->boolean('external_api_has_error')->default(false);
            $table->text('external_api_error_message')->nullable();
            $table->decimal('register_total', 12, 3)->default(0);
            $table->decimal('system_calculated_tip', 12, 3)->default(0);
            $table->decimal('final_difference', 12, 3)->default(0);
            $table->decimal('tolerance_used', 12, 3)->default(0);
            $table->string('verdict', 32);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('business_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finances');
    }
};
