<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_items')) {
            Schema::table('inventory_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('inventory_items', 'input_type')) {
                    $table->string('input_type')->default('number')->after('unit');
                }
                if (! Schema::hasColumn('inventory_items', 'options')) {
                    $table->json('options')->nullable()->after('input_type');
                }
            });
        }

        if (! Schema::hasTable('inventory_records')) {
            return;
        }

        if (Schema::hasColumn('inventory_records', 'value')) {
            return;
        }

        if (Schema::hasColumn('inventory_records', 'quantity')) {
            Schema::table('inventory_records', function (Blueprint $table): void {
                $table->string('value')->nullable()->after('date');
            });

            foreach (DB::table('inventory_records')->select('id', 'quantity')->cursor() as $row) {
                DB::table('inventory_records')->where('id', $row->id)->update([
                    'value' => $row->quantity !== null ? (string) $row->quantity : null,
                ]);
            }

            Schema::table('inventory_records', function (Blueprint $table): void {
                $table->dropColumn('quantity');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_records') && Schema::hasColumn('inventory_records', 'value') && ! Schema::hasColumn('inventory_records', 'quantity')) {
            Schema::table('inventory_records', function (Blueprint $table): void {
                $table->decimal('quantity', 8, 2)->nullable()->after('date');
            });

            foreach (DB::table('inventory_records')->select('id', 'value')->cursor() as $row) {
                $qty = null;
                if ($row->value !== null && $row->value !== '' && is_numeric($row->value)) {
                    $qty = $row->value;
                }
                DB::table('inventory_records')->where('id', $row->id)->update(['quantity' => $qty]);
            }

            Schema::table('inventory_records', function (Blueprint $table): void {
                $table->dropColumn('value');
            });
        }

        if (Schema::hasTable('inventory_items')) {
            Schema::table('inventory_items', function (Blueprint $table): void {
                if (Schema::hasColumn('inventory_items', 'options')) {
                    $table->dropColumn('options');
                }
                if (Schema::hasColumn('inventory_items', 'input_type')) {
                    $table->dropColumn('input_type');
                }
            });
        }
    }
};
