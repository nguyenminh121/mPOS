<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_sales_summaries', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->date('summary_date');
            $table->unsignedInteger('product_variant_id');
            $table->integer('quantity')->default(0);
            $table->decimal('revenue', 14, 2)->default(0.00);
            $table->decimal('cost', 14, 2)->default(0.00);
            $table->decimal('profit', 14, 2)->default(0.00);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'summary_date', 'product_variant_id'], 'uk_tenant_date_product');
            $table->index('summary_date', 'product_sales_summaries_summary_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sales_summaries');
    }
};
