<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('table_sessions')) {
            return;
        }
        Schema::table('table_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('table_sessions', 'management_source')) {
                $table->string('management_source', 16)->default('legacy')->after('session_revision');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('table_sessions')) {
            return;
        }
        Schema::table('table_sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('table_sessions', 'management_source')) {
                $table->dropColumn('management_source');
            }
        });
    }
};
