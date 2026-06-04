<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('default_price_list_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('default_price_list_id')->references('id')->on('price_lists')->nullOnDelete();
            $table->unique(['tenant_id', 'code'], 'uk_tenant_cust_group_code');
            $table->index('tenant_id', 'customer_groups_tenant_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_groups');
    }
};
