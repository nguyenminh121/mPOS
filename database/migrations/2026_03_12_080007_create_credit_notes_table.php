<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('code', 50)->unique();
            $table->unsignedInteger('invoice_id');
            $table->unsignedInteger('order_id')->nullable();
            $table->unsignedInteger('return_order_id')->nullable();
            $table->enum('type', ['RETURN', 'REFUND', 'DISCOUNT', 'ERROR']);
            $table->decimal('amount', 14, 2);
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('return_order_id')->references('id')->on('return_orders')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->index('invoice_id', 'credit_notes_invoice_id_index');
            $table->index('return_order_id', 'credit_notes_return_order_id_index');
            $table->index('type', 'credit_notes_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
