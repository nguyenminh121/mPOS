<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('code', 50)->nullable();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('tax_code', 50)->nullable();
            $table->decimal('debt_amount', 14, 2)->default(0.00);
            $table->decimal('credit_limit', 14, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'code'], 'uk_tenant_supplier_code');
            $table->index('tenant_id', 'suppliers_tenant_id_index');
            $table->index('name', 'suppliers_name_index');
            $table->index('is_active', 'suppliers_is_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
