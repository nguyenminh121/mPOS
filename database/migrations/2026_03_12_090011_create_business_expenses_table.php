<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_expenses', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 50);
            $table->decimal('amount', 14, 2);
            $table->unsignedInteger('order_id')->nullable();
            $table->unsignedInteger('supplier_id')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->enum('payment_status', ['PENDING', 'PAID', 'CANCELLED'])->default('PENDING');
            $table->timestamp('paid_at')->nullable();
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expense_date')->useCurrent();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->index('category', 'business_expenses_category_index');
            $table->index('payment_status', 'business_expenses_payment_status_index');
            $table->index('expense_date', 'business_expenses_expense_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_expenses');
    }
};
