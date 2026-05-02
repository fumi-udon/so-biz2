<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 冪等: 既に 2026_04_27_* で追加済みの環境では no-op。
 * 新規クローンや欠損 DB 向けに customer_name / customer_phone を保証する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('table_sessions', 'customer_name')) {
                $table->string('customer_name')->nullable()->after('staff_name');
            }
            if (! Schema::hasColumn('table_sessions', 'customer_phone')) {
                $table->string('customer_phone')->nullable()->after('customer_name');
            }
        });
    }

    public function down(): void
    {
        // 冪等追加のみ。カラム削除は 2026_04_27_* の down に委ねる（二重定義を避ける）。
    }
};
