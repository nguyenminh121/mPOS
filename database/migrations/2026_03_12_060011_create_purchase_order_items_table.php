<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('purchase_order_id');
            $table->unsignedInteger('product_variant_id');
            $table->string('unit_name', 50)->default('Cái');
            $table->integer('conversion_rate')->default(1);
            $table->integer('quantity');
            $table->integer('received_quantity')->default(0);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->decimal('tax_amount', 14, 2)->default(0.00);
            $table->decimal('total', 14, 2);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
            // product_variant_id FK deferred
            $table->index('purchase_order_id', 'purchase_order_items_purchase_order_id_index');
            $table->index('product_variant_id', 'purchase_order_items_product_variant_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
