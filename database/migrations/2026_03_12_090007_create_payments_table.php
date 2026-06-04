<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('code', 50)->unique();
            $table->unsignedInteger('order_id')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->decimal('amount', 14, 2);
            $table->enum('method', ['CASH', 'BANK_TRANSFER', 'COD', 'DEBT', 'OTHER']);
            $table->enum('status', ['PENDING', 'PARTIAL', 'COMPLETED', 'REFUNDED', 'CANCELLED'])->default('PENDING');
            $table->string('reference_code')->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->index('order_id', 'payments_order_id_index');
            $table->index('customer_id', 'payments_customer_id_index');
            $table->index('method', 'payments_method_index');
            $table->index('status', 'payments_status_index');
            $table->index('paid_at', 'payments_paid_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
