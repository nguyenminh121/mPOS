<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_take_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('stock_take_id');
            $table->unsignedInteger('product_variant_id');
            $table->integer('system_qty');
            $table->integer('actual_qty');
            $table->integer('diff_qty');
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('stock_take_id')->references('id')->on('stock_takes')->cascadeOnDelete();
            // product_variant_id FK deferred
            $table->index('stock_take_id', 'stock_take_items_stock_take_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_take_items');
    }
};
