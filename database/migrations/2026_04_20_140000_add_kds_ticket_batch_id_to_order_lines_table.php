<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_lines') && ! Schema::hasColumn('order_lines', 'kds_ticket_batch_id')) {
            Schema::table('order_lines', function (Blueprint $table) {
                $table->string('kds_ticket_batch_id', 36)
                    ->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_lines') && Schema::hasColumn('order_lines', 'kds_ticket_batch_id')) {
            Schema::table('order_lines', function (Blueprint $table) {
                $table->dropColumn('kds_ticket_batch_id');
            });
        }
    }
};
