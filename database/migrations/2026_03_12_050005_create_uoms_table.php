<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uoms', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('tenant_id');
            $table->string('name');
            $table->string('symbol', 50)->nullable();
            $table->unsignedBigInteger('base_uom_id')->nullable();
            $table->decimal('conversion_factor', 14, 6)->default(1.000000);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('base_uom_id')->references('id')->on('uoms')->restrictOnDelete();
            $table->unique(['tenant_id', 'name'], 'uk_tenant_uom_name');
            $table->unique(['tenant_id', 'symbol'], 'uk_tenant_uom_symbol');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uoms');
    }
};
