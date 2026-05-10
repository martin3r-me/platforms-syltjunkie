<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sj_shop_products', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->enum('product_type', ['physical', 'digital']);
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('compare_at_price_cents')->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->integer('stock_quantity')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('digital_file_id')->nullable();
            $table->json('extra_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'slug']);

            $table->foreign('digital_file_id')
                ->references('id')
                ->on('context_files')
                ->nullOnDelete();
        });

        Schema::create('sj_shop_product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('sj_shop_products')->cascadeOnDelete();
            $table->string('label', 100);
            $table->string('sku', 100)->nullable();
            $table->unsignedInteger('price_cents')->nullable();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->smallInteger('sort_order')->default(0);
            $table->json('options')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sj_shop_product_dimensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('sj_shop_products')->cascadeOnDelete();
            $table->string('name', 100);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sj_shop_product_dimension_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dimension_id')->constrained('sj_shop_product_dimensions')->cascadeOnDelete();
            $table->string('value', 100);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sj_shop_variant_dimension_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('sj_shop_product_variants')->cascadeOnDelete();
            $table->foreignId('dimension_value_id')->constrained('sj_shop_product_dimension_values')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['variant_id', 'dimension_value_id'], 'sj_shop_vdv_unique');
        });

        Schema::create('sj_shop_product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('sj_shop_products')->cascadeOnDelete();
            $table->foreignId('sj_image_id')->constrained('sj_images')->cascadeOnDelete();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['product_id', 'sj_image_id']);
        });

        Schema::create('sj_shop_orders', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->string('order_number', 30)->unique();
            $table->enum('status', ['pending', 'paid', 'shipped', 'completed', 'cancelled', 'refunded'])->default('pending');
            $table->string('customer_email');
            $table->string('customer_name');
            $table->json('shipping_address')->nullable();
            $table->unsignedInteger('subtotal_cents');
            $table->unsignedInteger('shipping_cents')->default(0);
            $table->unsignedInteger('total_cents');
            $table->char('currency', 3)->default('EUR');
            $table->string('payment_provider', 50)->nullable();
            $table->string('payment_reference')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('shipped_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sj_shop_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('sj_shop_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('product_name');
            $table->string('variant_label', 100)->nullable();
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('total_cents');
            $table->boolean('is_digital');
            $table->string('download_url', 500)->nullable();
            $table->timestamps();

            $table->foreign('product_id')
                ->references('id')
                ->on('sj_shop_products')
                ->nullOnDelete();

            $table->foreign('variant_id')
                ->references('id')
                ->on('sj_shop_product_variants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sj_shop_order_items');
        Schema::dropIfExists('sj_shop_orders');
        Schema::dropIfExists('sj_shop_product_images');
        Schema::dropIfExists('sj_shop_variant_dimension_values');
        Schema::dropIfExists('sj_shop_product_dimension_values');
        Schema::dropIfExists('sj_shop_product_dimensions');
        Schema::dropIfExists('sj_shop_product_variants');
        Schema::dropIfExists('sj_shop_products');
    }
};
