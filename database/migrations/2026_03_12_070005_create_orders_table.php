<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('code', 50)->unique();
            $table->unsignedInteger('parent_order_id')->nullable();
            $table->integer('revision_number')->default(0);
            $table->string('revision_reason', 50)->nullable();
            $table->unsignedInteger('invoice_id')->nullable()->unique();
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('warehouse_id');
            $table->enum('type', ['RETAIL', 'WHOLESALE', 'ONLINE', 'RETURN'])->default('RETAIL');
            $table->enum('status', ['PREORDER', 'DRAFT', 'CONFIRMED', 'PROCESSING', 'COMPLETED', 'CANCELLED'])->default('DRAFT');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->unsignedInteger('locked_by')->nullable();
            $table->string('locked_reason', 50)->nullable();
            $table->decimal('total_amount', 14, 2)->default(0.00);
            $table->decimal('discount', 14, 2)->default(0.00);
            $table->decimal('surcharge', 14, 2)->default(0.00);
            $table->decimal('final_amount', 14, 2)->default(0.00);
            $table->decimal('paid_amount', 14, 2)->default(0.00);
            $table->decimal('debt_amount', 14, 2)->default(0.00);
            $table->unsignedInteger('promotion_id')->nullable();
            $table->decimal('promo_discount', 14, 2)->default(0.00);
            $table->text('note')->nullable();
            $table->timestamp('order_date')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            // customer_id FK deferred (customers table created later)
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('promotion_id')->references('id')->on('promotions')->nullOnDelete();
            $table->foreign('parent_order_id')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('locked_by')->references('id')->on('users')->nullOnDelete();
            // invoice_id FK deferred (invoices table created later)

            $table->index('tenant_id', 'orders_tenant_id_index');
            $table->index('customer_id', 'orders_customer_id_index');
            $table->index('user_id', 'orders_user_id_index');
            $table->index('warehouse_id', 'orders_warehouse_id_index');
            $table->index('type', 'orders_type_index');
            $table->index('status', 'orders_status_index');
            $table->index('order_date', 'orders_order_date_index');
            $table->index('is_locked', 'orders_is_locked_index');
            $table->index('parent_order_id', 'orders_parent_order_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
