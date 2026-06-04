<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('category_id')->nullable();
            $table->string('barcode')->nullable();
            $table->string('sku', 100)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('base_unit', 50)->default('Cái');
            $table->string('origin', 100)->nullable();
            $table->string('brand', 100)->nullable();
            $table->unsignedInteger('tax_config_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('tax_config_id')->references('id')->on('tax_configs')->nullOnDelete();
            $table->unique(['tenant_id', 'barcode'], 'uk_tenant_product_barcode');
            $table->unique(['tenant_id', 'sku'], 'uk_tenant_product_sku');
            $table->index('tenant_id', 'products_tenant_id_index');
            $table->index('category_id', 'products_category_id_index');
            $table->index('name', 'products_name_index');
            $table->index('is_active', 'products_is_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
