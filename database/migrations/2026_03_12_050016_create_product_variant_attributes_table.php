<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_attributes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_variant_id');
            $table->unsignedInteger('attribute_id');
            $table->text('description')->nullable();
            $table->decimal('value_number', 14, 4)->nullable();
            $table->string('value_text', 500)->nullable();
            $table->date('value_date')->nullable();
            $table->boolean('value_bool')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('attribute_id')->references('id')->on('attributes')->cascadeOnDelete();
            $table->unique(['product_variant_id', 'attribute_id'], 'uk_variant_attribute');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_attributes');
    }
};
