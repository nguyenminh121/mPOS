<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_id');
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->string('base_unit', 50);
            $table->decimal('price', 14, 2);
            $table->decimal('discount_price', 14, 2)->nullable();
            $table->decimal('cost_price', 14, 2)->default(0.00);
            $table->decimal('wholesale_price', 14, 2)->default(0.00);
            $table->string('currency', 10)->default('VND');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->index('product_id', 'product_variants_product_id_index');
            $table->index('sku', 'product_variants_sku_index');
            $table->index('barcode', 'product_variants_barcode_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
