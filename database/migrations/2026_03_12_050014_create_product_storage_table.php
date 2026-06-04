<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_storage', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_variant_id');
            $table->unsignedInteger('warehouse_location_id');
            $table->integer('stock_level')->default(0);
            $table->integer('min_stock_level')->default(0);
            $table->integer('max_stock_level')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            // FK warehouse_location_id → warehouse_locations(id) deferred to warehouses migration
            $table->unique(['product_variant_id', 'warehouse_location_id'], 'uk_variant_location');
            $table->index('product_variant_id', 'product_storage_variant_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_storage');
    }
};
