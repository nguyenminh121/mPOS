<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_order_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('return_order_id');
            $table->unsignedInteger('product_variant_id');
            $table->unsignedInteger('order_item_id')->nullable();
            $table->integer('quantity');
            $table->decimal('price', 14, 2);
            $table->decimal('refund_amount', 14, 2);
            $table->string('condition', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('return_order_id')->references('id')->on('return_orders')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('order_item_id')->references('id')->on('order_items')->nullOnDelete();
            $table->index('return_order_id', 'return_order_items_return_order_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_order_items');
    }
};
