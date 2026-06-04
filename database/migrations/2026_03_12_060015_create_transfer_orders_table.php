<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('code', 50)->unique();
            $table->unsignedInteger('source_warehouse_id');
            $table->unsignedInteger('destination_warehouse_id');
            $table->enum('status', ['DRAFT', 'APPROVED', 'IN_TRANSIT', 'RECEIVED', 'CANCELLED'])->default('DRAFT');
            $table->integer('total_items')->default(0);
            $table->text('note')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('source_warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('destination_warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->index('tenant_id', 'transfer_orders_tenant_id_index');
            $table->index('status', 'transfer_orders_status_index');
            $table->index('source_warehouse_id', 'transfer_orders_source_warehouse_id_index');
            $table->index('destination_warehouse_id', 'transfer_orders_destination_warehouse_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_orders');
    }
};
