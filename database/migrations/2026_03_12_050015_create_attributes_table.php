<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('code', 50);
            $table->string('name');
            $table->string('unit', 50)->nullable();
            $table->enum('data_type', ['NUMBER', 'TEXT', 'DATE', 'BOOLEAN'])->default('TEXT');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'code'], 'uk_tenant_attribute_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
