<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['DISCOUNT_ITEM', 'DISCOUNT_ORDER', 'BUY_X_GET_Y', 'FREE_SHIPPING', 'GIFT']);
            $table->enum('discount_type', ['PERCENT', 'FIXED_AMOUNT']);
            $table->decimal('value', 14, 2);
            $table->decimal('min_order_value', 14, 2)->nullable();
            $table->decimal('max_discount', 14, 2)->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->integer('usage_limit')->nullable();
            $table->integer('used_count')->default(0);
            $table->string('target_customer_type', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['tenant_id', 'code'], 'uk_tenant_promotion_code');
            $table->index('is_active', 'promotions_is_active_index');
            $table->index('start_date', 'promotions_start_date_index');
            $table->index('end_date', 'promotions_end_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
