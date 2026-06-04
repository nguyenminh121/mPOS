<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_takes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('code', 50)->unique();
            $table->unsignedInteger('warehouse_id');
            $table->unsignedInteger('creator_id');
            $table->enum('status', ['DRAFT', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'])->default('DRAFT');
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('completed_at')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('creator_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('warehouse_id', 'stock_takes_warehouse_id_index');
            $table->index('status', 'stock_takes_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_takes');
    }
};
