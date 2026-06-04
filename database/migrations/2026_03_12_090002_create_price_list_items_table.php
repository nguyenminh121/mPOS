<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_list_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('price_list_id');
            $table->unsignedInteger('product_variant_id');
            $table->string('unit_name', 50)->default('Cái');
            $table->decimal('price', 14, 2);
            $table->integer('min_quantity')->default(1);

            $table->foreign('price_list_id')->references('id')->on('price_lists')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->unique(['price_list_id', 'product_variant_id', 'unit_name', 'min_quantity'], 'uk_pricelist_product_unit_qty');
            $table->index('price_list_id', 'price_list_items_price_list_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_list_items');
    }
};
