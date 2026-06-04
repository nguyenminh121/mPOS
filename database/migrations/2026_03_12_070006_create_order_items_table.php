<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id');
            $table->unsignedInteger('product_variant_id');
            $table->unsignedInteger('batch_id')->nullable();
            $table->string('unit_name', 50)->default('Cái');
            $table->integer('conversion_rate')->default(1);
            $table->integer('quantity');
            $table->integer('returned_quantity')->default(0);
            $table->decimal('price', 14, 2);
            $table->decimal('cost_price', 14, 2)->default(0.00);
            $table->decimal('discount', 14, 2)->default(0.00);
            $table->decimal('promo_discount', 14, 2)->default(0.00);
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->decimal('tax_amount', 14, 2)->default(0.00);
            $table->decimal('total', 14, 2);
            $table->unsignedInteger('promotion_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('batch_id')->references('id')->on('stock_batches')->nullOnDelete();
            $table->foreign('promotion_id')->references('id')->on('promotions')->nullOnDelete();
            $table->index('order_id', 'order_items_order_id_index');
            $table->index('product_variant_id', 'order_items_product_variant_id_index');
            $table->index('batch_id', 'order_items_batch_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
