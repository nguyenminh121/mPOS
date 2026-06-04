<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_shifts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('warehouse_id');
            $table->unsignedInteger('user_id');
            $table->enum('status', ['OPEN', 'SUSPENDED', 'CLOSED'])->default('OPEN');
            $table->decimal('opening_cash', 14, 2)->default(0.00);
            $table->decimal('closing_cash', 14, 2)->nullable();
            $table->decimal('expected_cash', 14, 2)->nullable();
            $table->decimal('cash_difference', 14, 2)->nullable();
            $table->decimal('total_sales', 14, 2)->default(0.00);
            $table->decimal('total_returns', 14, 2)->default(0.00);
            $table->integer('total_transactions')->default(0);
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->text('note')->nullable();
            $table->unsignedInteger('closed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();
            $table->index('tenant_id', 'pos_shifts_tenant_id_index');
            $table->index('warehouse_id', 'pos_shifts_warehouse_id_index');
            $table->index('user_id', 'pos_shifts_user_id_index');
            $table->index('status', 'pos_shifts_status_index');
            $table->index('opened_at', 'pos_shifts_opened_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_shifts');
    }
};
