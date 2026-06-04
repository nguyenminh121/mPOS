<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_variant_id');
            $table->decimal('cost_price', 14, 2);
            $table->decimal('retail_price', 14, 2);
            $table->decimal('wholesale_price', 14, 2);
            $table->unsignedBigInteger('uom_id');
            $table->string('currency', 10);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('uom_id')->references('id')->on('uoms')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->unique(['product_variant_id', 'uom_id'], 'uk_variant_uom_price');
            $table->index('product_variant_id', 'product_prices_variant_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
