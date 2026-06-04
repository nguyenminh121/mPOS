<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_variant_id');
            $table->unsignedInteger('warehouse_id');
            $table->unsignedInteger('warehouse_location_id')->nullable();
            $table->integer('quantity')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            // product_variant_id FK deferred to final migration
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('warehouse_location_id')->references('id')->on('warehouse_locations')->nullOnDelete();
            $table->unique(['product_variant_id', 'warehouse_id'], 'uk_product_warehouse');
            $table->index('warehouse_id', 'stocks_warehouse_id_index');
            $table->index('quantity', 'stocks_quantity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
