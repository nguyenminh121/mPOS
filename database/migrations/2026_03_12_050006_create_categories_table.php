<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('code', 50)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('parent_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('categories')->restrictOnDelete();
            $table->unique(['tenant_id', 'code'], 'uk_tenant_category_code');
            $table->index('tenant_id', 'categories_tenant_id_index');
            $table->index('parent_id', 'categories_parent_id_index');
            $table->index('is_active', 'categories_is_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
