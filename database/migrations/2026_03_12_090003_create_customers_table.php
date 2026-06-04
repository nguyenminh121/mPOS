<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('code', 50)->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('type', 50);
            $table->string('tax_code', 50)->nullable();
            $table->string('company_name')->nullable();
            $table->decimal('debt_amount', 14, 2)->default(0.00);
            $table->decimal('total_spent', 14, 2)->default(0.00);
            $table->decimal('credit_limit', 14, 2)->default(0.00);
            $table->integer('default_due_days')->default(0);
            $table->text('description')->nullable();
            $table->unsignedInteger('group_id')->nullable();
            $table->unsignedInteger('price_list_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('price_list_id')->references('id')->on('price_lists')->nullOnDelete();
            // group_id FK deferred (customer_groups created after)
            $table->unique(['tenant_id', 'code'], 'uk_tenant_customer_code');
            $table->unique(['tenant_id', 'phone'], 'uk_tenant_customer_phone');
            $table->index('tenant_id', 'customers_tenant_id_index');
            $table->index('type', 'customers_type_index');
            $table->index('is_active', 'customers_is_active_index');
            $table->index('name', 'customers_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
