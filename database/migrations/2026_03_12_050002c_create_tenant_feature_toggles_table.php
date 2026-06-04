<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_feature_toggles', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('feature_id');
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('disabled_at')->nullable();
            $table->unsignedInteger('disabled_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('feature_id')->references('id')->on('features')->cascadeOnDelete();
            $table->foreign('disabled_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['tenant_id', 'feature_id'], 'uk_tenant_feature');
            $table->index('tenant_id', 'tenant_feature_toggles_tenant_id_index');
            $table->index('is_enabled', 'tenant_feature_toggles_is_enabled_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_feature_toggles');
    }
};
