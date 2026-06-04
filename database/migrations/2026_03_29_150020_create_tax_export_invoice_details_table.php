<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_export_invoice_details', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('tax_export_batch_id');
            $table->unsignedInteger('invoice_id');
            $table->unsignedInteger('order_id')->nullable();
            $table->string('invoice_code', 50);
            $table->string('order_code', 50)->nullable();
            $table->string('customer_name', 255)->nullable();
            $table->decimal('invoice_total', 14, 2)->default(0);
            $table->timestamp('invoice_sale_at')->nullable();
            $table->enum('exported_status', ['EXPORTED', 'SKIPPED', 'FAILED'])->default('EXPORTED');
            $table->string('cqt_reference', 120)->nullable();
            $table->string('provider_reference', 120)->nullable();
            $table->text('error_message')->nullable();
            $table->longText('payload_snapshot')->nullable();
            $table->longText('response_snapshot')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('tax_export_batch_id')->references('id')->on('tax_export_batches')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();

            $table->unique(['tax_export_batch_id', 'invoice_id'], 'uk_tax_export_batch_invoice');
            $table->index('tenant_id', 'tax_export_invoice_details_tenant_id_index');
            $table->index('invoice_id', 'tax_export_invoice_details_invoice_id_index');
            $table->index('order_id', 'tax_export_invoice_details_order_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_export_invoice_details');
    }
};
