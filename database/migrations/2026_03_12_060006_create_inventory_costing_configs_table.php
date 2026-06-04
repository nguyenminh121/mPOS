<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_costing_configs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->enum('method', ['FIFO', 'LIFO', 'WEIGHTED_AVG', 'SPECIFIC'])->default('FIFO');
            $table->enum('pricing_strategy', ['GLOBAL', 'PER_BATCH', 'MARGIN_BASED'])->default('GLOBAL');
            $table->decimal('default_margin_percent', 5, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique('tenant_id', 'uk_tenant_costing');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_costing_configs');
    }
};
