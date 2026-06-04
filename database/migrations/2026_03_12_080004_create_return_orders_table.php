<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('code', 50)->unique();
            $table->unsignedInteger('order_id');
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('warehouse_id')->nullable();
            $table->enum('type', ['FULL', 'PARTIAL'])->default('PARTIAL');
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'COMPLETED'])->default('PENDING');
            $table->decimal('total_amount', 14, 2)->default(0.00);
            $table->enum('refund_method', ['CASH', 'BANK_TRANSFER', 'STORE_CREDIT'])->nullable();
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('return_date')->useCurrent();
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            // customer_id FK deferred
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->index('order_id', 'return_orders_order_id_index');
            $table->index('customer_id', 'return_orders_customer_id_index');
            $table->index('status', 'return_orders_status_index');
            $table->index('return_date', 'return_orders_return_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_orders');
    }
};
