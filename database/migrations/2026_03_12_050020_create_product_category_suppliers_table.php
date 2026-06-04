<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_category_suppliers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('category_id');
            $table->unsignedInteger('supplier_id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
            $table->unique(['category_id', 'supplier_id'], 'uk_category_supplier');
            $table->index('category_id', 'product_category_suppliers_category_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_category_suppliers');
    }
};
