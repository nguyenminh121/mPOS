<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SECTION 1: Multi-tenancy (stancl/tenancy)
     * Creates: tenants, domains, tenant_profiles
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            $table->string('name');
            $table->string('domain')->unique();
            $table->string('email')->unique();
            $table->enum('status', ['active', 'suspended', 'expired'])->default('active');
            $table->json('data')->nullable();
            $table->timestamps();
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->increments('id');
            $table->string('domain')->unique();
            $table->unsignedInteger('tenant_id');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::create('tenant_profiles', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('company_name');
            $table->text('company_address')->nullable();
            $table->string('company_phone', 20)->nullable();
            $table->string('company_email')->nullable();
            $table->string('tax_code', 50)->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_profiles');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('tenants');
    }
};
