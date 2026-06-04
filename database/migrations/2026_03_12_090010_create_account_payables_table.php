<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_payables', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('supplier_id');
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->enum('type', ['IMPORT', 'PAYMENT', 'DEBIT_NOTE', 'CREDIT_NOTE', 'ADJUST', 'WRITE_OFF']);
            $table->enum('status', ['OPEN', 'PARTIAL', 'PAID', 'OVERDUE', 'WRITTEN_OFF'])->default('OPEN');
            $table->unsignedInteger('import_receipt_id')->nullable();
            $table->unsignedInteger('payment_id')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('note')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
            $table->foreign('import_receipt_id')->references('id')->on('import_receipts')->nullOnDelete();
            // payment_id FK deferred
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->index('tenant_id', 'account_payables_tenant_id_index');
            $table->index('supplier_id', 'account_payables_supplier_id_index');
            $table->index('type', 'account_payables_type_index');
            $table->index('status', 'account_payables_status_index');
            $table->index('due_date', 'account_payables_due_date_index');
            $table->index('created_at', 'account_payables_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_payables');
    }
};
