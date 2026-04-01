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

        Schema::table('finances', function (Blueprint $table): void {
            if (! Schema::hasColumn('finances', 'close_status')) {
                $table->string('close_status', 16)->default('failed')->after('verdict');
            }

            if (! Schema::hasColumn('finances', 'system_tip_amount')) {
                $table->decimal('system_tip_amount', 12, 3)->nullable()->after('system_calculated_tip');
            }

            if (! Schema::hasColumn('finances', 'declared_tip_amount')) {
                $table->decimal('declared_tip_amount', 12, 3)->nullable()->after('system_tip_amount');
            }

            if (! Schema::hasColumn('finances', 'final_tip_amount')) {
                $table->decimal('final_tip_amount', 12, 3)->nullable()->after('declared_tip_amount');
            }

            if (! Schema::hasColumn('finances', 'reserve_amount')) {
                $table->decimal('reserve_amount', 12, 3)->default(0)->after('final_tip_amount');
            }

            if (! Schema::hasColumn('finances', 'failure_reason')) {
                $table->string('failure_reason', 255)->nullable()->after('reserve_amount');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('finances')) {
            return;
        }

        Schema::table('finances', function (Blueprint $table): void {
            foreach ([
                'close_status',
                'system_tip_amount',
                'declared_tip_amount',
                'final_tip_amount',
                'reserve_amount',
                'failure_reason',
            ] as $column) {
                if (Schema::hasColumn('finances', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

