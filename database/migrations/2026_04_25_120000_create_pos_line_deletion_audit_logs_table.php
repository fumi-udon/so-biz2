<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_line_deletion_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('table_session_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_line_id');
            $table->unsignedBigInteger('removed_by_user_id')->nullable();
            $table->unsignedBigInteger('approver_staff_id')->nullable();
            $table->string('approval_mode', 16)->default('open');
            $table->boolean('was_printed')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['shop_id', 'table_session_id'], 'pos_del_audit_idx');
            $table->index(['shop_id', 'created_at'], 'pos_del_shop_created_idx');
            $table->index(['order_line_id'], 'pos_del_order_line_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_line_deletion_audit_logs')) {
            Schema::table('pos_line_deletion_audit_logs', function (Blueprint $table): void {
                if (Schema::hasIndex('pos_line_deletion_audit_logs', 'pos_del_audit_idx')) {
                    $table->dropIndex('pos_del_audit_idx');
                }
                if (Schema::hasIndex('pos_line_deletion_audit_logs', 'pos_del_shop_created_idx')) {
                    $table->dropIndex('pos_del_shop_created_idx');
                }
                if (Schema::hasIndex('pos_line_deletion_audit_logs', 'pos_del_order_line_idx')) {
                    $table->dropIndex('pos_del_order_line_idx');
                }
            });
        }
        Schema::dropIfExists('pos_line_deletion_audit_logs');
    }
};
