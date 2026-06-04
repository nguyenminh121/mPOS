<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_batch_prices', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('batch_id');
            $table->string('unit_name', 50);
            $table->integer('conversion_rate')->default(1);
            $table->decimal('cost_price', 14, 2);
            $table->decimal('selling_price', 14, 2)->nullable();
            $table->decimal('wholesale_price', 14, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('batch_id')->references('id')->on('stock_batches')->cascadeOnDelete();
            $table->unique(['batch_id', 'unit_name'], 'uk_batch_unit');
            $table->index('batch_id', 'stock_batch_prices_batch_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_batch_prices');
    }
};
