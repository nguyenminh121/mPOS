<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stores')) {
            Schema::create('stores', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->string('code', 50);
                $table->string('name');
                $table->enum('type', ['HEADQUARTER', 'BRANCH', 'OUTLET', 'WAREHOUSE_ONLY'])->default('OUTLET');
                $table->text('address')->nullable();
                $table->string('phone', 20)->nullable();
                $table->string('email')->nullable();
                $table->string('manager_name', 100)->nullable();
                $table->string('timezone', 64)->default('Asia/Ho_Chi_Minh');
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->softDeletes();

                $table->foreign('tenant_id', 'stores_tenant_id_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->unique(['tenant_id', 'code'], 'uk_tenant_store_code');
                $table->index('tenant_id', 'stores_tenant_id_index');
                $table->index('type', 'stores_type_index');
                $table->index('is_active', 'stores_is_active_index');
            });
        }

        if (! Schema::hasTable('store_user')) {
            Schema::create('store_user', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('store_id');
                $table->unsignedInteger('user_id');
                $table->enum('role', ['OWNER', 'MANAGER', 'STAFF'])->default('STAFF');
                $table->boolean('is_primary')->default(false);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->foreign('tenant_id', 'store_user_tenant_id_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('store_id', 'store_user_store_id_fk')->references('id')->on('stores')->cascadeOnDelete();
                $table->foreign('user_id', 'store_user_user_id_fk')->references('id')->on('users')->cascadeOnDelete();
                $table->unique(['store_id', 'user_id'], 'uk_store_user');
                $table->index('tenant_id', 'store_user_tenant_id_index');
                $table->index('user_id', 'store_user_user_id_index');
                $table->index('role', 'store_user_role_index');
            });
        }

        if (Schema::hasTable('warehouses') && ! Schema::hasColumn('warehouses', 'store_id')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->unsignedInteger('store_id')->nullable()->after('tenant_id');
                $table->foreign('store_id', 'warehouses_store_id_fk')->references('id')->on('stores')->nullOnDelete();
                $table->index('store_id', 'warehouses_store_id_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('warehouses') && Schema::hasColumn('warehouses', 'store_id')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->dropForeign('warehouses_store_id_fk');
                $table->dropIndex('warehouses_store_id_index');
                $table->dropColumn('store_id');
            });
        }

        Schema::dropIfExists('store_user');
        Schema::dropIfExists('stores');
    }
};
