<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_receipts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('code', 50)->unique();
            $table->unsignedInteger('purchase_order_id')->nullable();
            $table->unsignedInteger('supplier_id')->nullable();
            $table->unsignedInteger('warehouse_id');
            $table->unsignedInteger('creator_id');
            $table->decimal('total_amount', 14, 2)->default(0.00);
            $table->decimal('paid_amount', 14, 2)->default(0.00);
            $table->decimal('debt_amount', 14, 2)->default(0.00);
            $table->enum('status', ['DRAFT', 'PENDING', 'APPROVED', 'COMPLETED', 'CANCELLED'])->default('DRAFT');
            $table->timestamp('import_date')->useCurrent();
            $table->text('note')->nullable();
            $table->unsignedInteger('parent_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('creator_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('import_receipts')->nullOnDelete();
            $table->index('purchase_order_id', 'import_receipts_purchase_order_id_index');
            $table->index('supplier_id', 'import_receipts_supplier_id_index');
            $table->index('warehouse_id', 'import_receipts_warehouse_id_index');
            $table->index('status', 'import_receipts_status_index');
            $table->index('import_date', 'import_receipts_import_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_receipts');
    }
};
