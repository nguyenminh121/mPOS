<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_order_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('transfer_order_id');
            $table->unsignedInteger('product_variant_id');
            $table->unsignedInteger('batch_id')->nullable();
            $table->integer('quantity');
            $table->integer('received_quantity')->default(0);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('transfer_order_id')->references('id')->on('transfer_orders')->cascadeOnDelete();
            // product_variant_id FK deferred
            $table->foreign('batch_id')->references('id')->on('stock_batches')->nullOnDelete();
            $table->index('transfer_order_id', 'transfer_order_items_transfer_order_id_index');
            $table->index('product_variant_id', 'transfer_order_items_product_variant_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_order_items');
    }
};
