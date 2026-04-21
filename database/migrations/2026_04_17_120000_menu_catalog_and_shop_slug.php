<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });

        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['shop_id', 'slug']);
        });

        Schema::create('dietary_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->string('icon_disk')->default('public');
            $table->string('icon_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('slug');
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Column order follows declaration order. Do not use ->after() inside Schema::create
            // (MySQL/Laravel emit invalid CREATE TABLE SQL). ALTER migrations may use ->after().
            $table->string('kitchen_name', 255)->nullable();
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('hero_image_disk')->default('public');
            $table->string('hero_image_path')->nullable();
            $table->unsignedInteger('from_price_minor')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('allergy_note')->nullable();
            $table->json('options_payload')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'slug']);
        });

        Schema::create('menu_item_dietary_badge', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dietary_badge_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['menu_item_id', 'dietary_badge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_dietary_badge');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('dietary_badges');
        Schema::dropIfExists('menu_categories');

        Schema::table('shops', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
