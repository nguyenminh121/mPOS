<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('code', 50)->unique();
            $table->unsignedInteger('order_id')->nullable()->unique();
            $table->unsignedInteger('root_invoice_id')->nullable();
            $table->unsignedInteger('parent_invoice_id')->nullable();
            $table->integer('version')->default(0);
            $table->unsignedInteger('customer_id');
            $table->decimal('subtotal', 14, 2);
            $table->unsignedInteger('tax_config_id')->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->decimal('tax_amount', 14, 2)->default(0.00);
            $table->decimal('total_amount', 14, 2);
            $table->enum('status', ['ISSUED', 'REPLACED', 'CANCELLED'])->default('ISSUED');
            $table->text('change_reason')->nullable();
            $table->timestamp('sale_at');
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            // customer_id FK deferred
            $table->foreign('root_invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->foreign('parent_invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            // tax_config_id FK deferred
            $table->index('tenant_id', 'invoices_tenant_id_index');
            $table->index('order_id', 'invoices_order_id_index');
            $table->index('customer_id', 'invoices_customer_id_index');
            $table->index('status', 'invoices_status_index');
            $table->index('sale_at', 'invoices_sale_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
