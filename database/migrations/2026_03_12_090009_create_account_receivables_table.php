<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_receivables', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('customer_id');
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->enum('type', ['INVOICE', 'PAYMENT', 'CREDIT_NOTE', 'DEBIT_NOTE', 'ADJUST', 'WRITE_OFF']);
            $table->enum('status', ['OPEN', 'PARTIAL', 'PAID', 'OVERDUE', 'WRITTEN_OFF'])->default('OPEN');
            $table->unsignedInteger('order_id')->nullable();
            $table->unsignedInteger('invoice_id')->nullable();
            $table->unsignedInteger('payment_id')->nullable();
            $table->unsignedInteger('credit_note_id')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('note')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            // customer_id FK deferred
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
            // payment_id FK deferred
            $table->foreign('credit_note_id')->references('id')->on('credit_notes')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->index('tenant_id', 'account_receivables_tenant_id_index');
            $table->index('customer_id', 'account_receivables_customer_id_index');
            $table->index('type', 'account_receivables_type_index');
            $table->index('status', 'account_receivables_status_index');
            $table->index('due_date', 'account_receivables_due_date_index');
            $table->index('created_at', 'account_receivables_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_receivables');
    }
};
