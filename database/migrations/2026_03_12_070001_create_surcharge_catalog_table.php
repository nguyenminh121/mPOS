<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surcharge_catalog', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['PERCENT', 'FIXED_AMOUNT']);
            $table->decimal('value', 14, 2);
            $table->boolean('is_auto_apply')->default(false);
            $table->boolean('is_refundable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('category', 50)->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['tenant_id', 'code'], 'uk_tenant_surcharge_code');
            $table->index('is_active', 'surcharge_catalog_is_active_index');
            $table->index('category', 'surcharge_catalog_category_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surcharge_catalog');
    }
};
