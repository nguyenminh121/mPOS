<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_price_history', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_variant_id');
            $table->unsignedInteger('product_unit_id')->nullable();
            $table->string('unit_name', 50);
            $table->integer('conversion_rate');
            $table->decimal('cost_price', 14, 2);
            $table->decimal('retail_price', 14, 2);
            $table->decimal('wholesale_price', 14, 2);
            $table->timestamp('effective_date')->useCurrent();
            $table->unsignedInteger('import_receipt_id')->nullable();
            $table->string('change_reason', 50)->nullable();
            $table->unsignedInteger('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            // FK import_receipt_id → import_receipts(id) deferred to later migration
            $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['product_variant_id', 'unit_name', 'effective_date'], 'unit_price_history_product_effective_index');
            $table->index('effective_date', 'unit_price_history_effective_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_price_history');
    }
};
