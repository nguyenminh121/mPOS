<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_variant_id');
            $table->unsignedBigInteger('uom_id');
            $table->decimal('conversion_factor', 14, 6)->default(1.000000);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('uom_id')->references('id')->on('uoms')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->unique(['product_variant_id', 'uom_id'], 'uk_variant_uom');
            $table->index('product_variant_id', 'product_units_variant_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
