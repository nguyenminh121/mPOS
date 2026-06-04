<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_sales_summaries', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->date('summary_date');
            $table->unsignedInteger('category_id');
            $table->decimal('revenue', 14, 2)->default(0.00);
            $table->decimal('profit', 14, 2)->default(0.00);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->unique(['tenant_id', 'summary_date', 'category_id'], 'uk_tenant_date_category');
            $table->index('summary_date', 'category_sales_summaries_summary_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_sales_summaries');
    }
};
