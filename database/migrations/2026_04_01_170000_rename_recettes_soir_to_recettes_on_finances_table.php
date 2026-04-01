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

        if (Schema::hasColumn('finances', 'recettes_soir') && ! Schema::hasColumn('finances', 'recettes')) {
            Schema::table('finances', function (Blueprint $table) {
                $table->renameColumn('recettes_soir', 'recettes');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('finances')) {
            return;
        }

        if (Schema::hasColumn('finances', 'recettes') && ! Schema::hasColumn('finances', 'recettes_soir')) {
            Schema::table('finances', function (Blueprint $table) {
                $table->renameColumn('recettes', 'recettes_soir');
            });
        }
    }
};
