<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && ! Schema::hasTable('legacy_orders')) {
            Schema::rename('orders', 'legacy_orders');
        }

        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['shop_id', 'name']);
            $table->index(['shop_id', 'sort_order']);
        });

        Schema::create('table_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_table_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('status', 32);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'status']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_session_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32);
            $table->unsignedBigInteger('total_price_minor');
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();

            $table->index(['table_session_id', 'status']);
            $table->index(['shop_id', 'created_at']);
        });

        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->string('snapshot_name');
            $table->string('snapshot_kitchen_name');
            $table->json('snapshot_options_payload');
            $table->string('status', 32);
            $table->timestamps();

            $table->index(['order_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('table_sessions');
        Schema::dropIfExists('restaurant_tables');

        if (Schema::hasTable('legacy_orders') && ! Schema::hasTable('orders')) {
            Schema::rename('legacy_orders', 'orders');
        }
    }
};
