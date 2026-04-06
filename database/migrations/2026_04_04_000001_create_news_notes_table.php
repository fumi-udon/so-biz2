<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('body');
            $table->date('posted_date');
            $table->timestamps();

            $table->index('posted_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_notes');
    }
};
