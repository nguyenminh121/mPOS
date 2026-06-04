<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('subscription_id')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10)->default('VND');
            $table->enum('status', ['paid', 'unpaid', 'failed', 'refunded'])->default('unpaid');
            $table->string('invoice_number')->unique();
            $table->string('payment_method', 50)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->nullOnDelete();
            $table->index('tenant_id', 'billing_tenant_id_index');
            $table->index('status', 'billing_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing');
    }
};
