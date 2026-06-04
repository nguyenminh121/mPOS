<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_variant_id');
            $table->unsignedInteger('warehouse_id');
            $table->unsignedInteger('import_item_id')->nullable();
            $table->string('batch_code', 100)->nullable();
            $table->decimal('cost_price', 14, 2);
            $table->integer('initial_quantity');
            $table->integer('remaining_quantity');
            $table->decimal('selling_price', 14, 2)->nullable();
            $table->decimal('wholesale_price', 14, 2)->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->date('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            // product_variant_id, import_item_id FKs deferred to final migration
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->index(['product_variant_id', 'warehouse_id'], 'stock_batches_product_warehouse_index');
            $table->index('remaining_quantity', 'stock_batches_remaining_index');
            $table->index('is_active', 'stock_batches_is_active_index');
            $table->index('received_at', 'stock_batches_received_at_index');
            $table->index('expires_at', 'stock_batches_expires_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
    }
};
