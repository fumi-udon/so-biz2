<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_records', function (Blueprint $table) {
            $table->string('value')->nullable()->after('date');
        });

        foreach (DB::table('inventory_records')->select('id', 'quantity')->cursor() as $row) {
            DB::table('inventory_records')->where('id', $row->id)->update([
                'value' => $row->quantity !== null ? (string) $row->quantity : null,
            ]);
        }

        Schema::table('inventory_records', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_records', function (Blueprint $table) {
            $table->decimal('quantity', 8, 2)->nullable()->after('date');
        });

        foreach (DB::table('inventory_records')->select('id', 'value')->cursor() as $row) {
            $qty = null;
            if ($row->value !== null && $row->value !== '' && is_numeric($row->value)) {
                $qty = $row->value;
            }
            DB::table('inventory_records')->where('id', $row->id)->update(['quantity' => $qty]);
        }

        Schema::table('inventory_records', function (Blueprint $table) {
            $table->dropColumn('value');
        });
    }
};
