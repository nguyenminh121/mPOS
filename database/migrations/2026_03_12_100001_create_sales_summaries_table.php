<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_summaries', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->date('summary_date');
            $table->integer('total_orders')->default(0);
            $table->integer('total_items')->default(0);
            $table->decimal('gross_revenue', 14, 2)->default(0.00);
            $table->decimal('discount_total', 14, 2)->default(0.00);
            $table->decimal('net_revenue', 14, 2)->default(0.00);
            $table->decimal('cost_total', 14, 2)->default(0.00);
            $table->decimal('profit', 14, 2)->default(0.00);
            $table->decimal('retail_revenue', 14, 2)->default(0.00);
            $table->decimal('wholesale_revenue', 14, 2)->default(0.00);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'summary_date'], 'uk_tenant_summary_date');
            $table->index('summary_date', 'sales_summaries_summary_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_summaries');
    }
};
