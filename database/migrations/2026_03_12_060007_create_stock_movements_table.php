<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_variant_id');
            $table->unsignedInteger('warehouse_id');
            $table->unsignedInteger('batch_id')->nullable();
            $table->enum('movement_type', ['IMPORT', 'EXPORT', 'RETURN_IN', 'RETURN_OUT', 'ADJUST_IN', 'ADJUST_OUT', 'TRANSFER_IN', 'TRANSFER_OUT', 'DAMAGED', 'EXPIRED']);
            $table->integer('quantity');
            $table->integer('balance_after');
            $table->decimal('cost_price_at_move', 14, 2)->nullable();
            $table->unsignedInteger('order_id')->nullable();
            $table->unsignedInteger('import_item_id')->nullable();
            $table->unsignedInteger('stock_take_id')->nullable();
            $table->text('note')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // product_variant_id, order_id, import_item_id, stock_take_id FKs deferred
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('batch_id')->references('id')->on('stock_batches')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index('product_variant_id', 'stock_movements_product_variant_id_index');
            $table->index('warehouse_id', 'stock_movements_warehouse_id_index');
            $table->index('movement_type', 'stock_movements_movement_type_index');
            $table->index('created_at', 'stock_movements_created_at_index');
            $table->index('order_id', 'stock_movements_order_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
