<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('finances')) {
            return;
        }

        if (! Schema::hasColumn('finances', 'close_snapshot')) {
            Schema::table('finances', function (Blueprint $table) {
                $table->json('close_snapshot')->nullable()->after('verdict');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('finances')) {
            return;
        }

        if (Schema::hasColumn('finances', 'close_snapshot')) {
            Schema::table('finances', function (Blueprint $table) {
                $table->dropColumn('close_snapshot');
            });
        }
    }
};
