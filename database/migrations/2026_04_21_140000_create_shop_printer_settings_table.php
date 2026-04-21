<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_printer_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('printer_ip', 64);
            $table->string('printer_port', 8)->default('8043');
            $table->string('device_id', 64)->default('local_printer');
            $table->boolean('crypto')->default(true);
            $table->unsignedInteger('timeout_ms')->default(10_000);
            $table->timestamps();

            $table->unique('shop_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_printer_settings');
    }
};
