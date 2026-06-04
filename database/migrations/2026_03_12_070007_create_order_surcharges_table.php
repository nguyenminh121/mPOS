<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_surcharges', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id');
            $table->string('name');
            $table->enum('type', ['PERCENT', 'FIXED_AMOUNT']);
            $table->decimal('value', 14, 2);
            $table->decimal('amount', 14, 2);
            $table->boolean('is_refundable')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->index('order_id', 'order_surcharges_order_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_surcharges');
    }
};
