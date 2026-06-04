<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plans')) {
            Schema::create('plans', function (Blueprint $table) {
                $table->increments('id');
                $table->string('code', 100)->unique();
                $table->string('name');
                $table->enum('tier', ['FREE', 'BASIC', 'PREMIUM', 'ENTERPRISE'])->default('BASIC');
                $table->enum('db_mode', ['SHARED', 'DEDICATED'])->default('SHARED');
                $table->enum('billing_cycle', ['MONTHLY', 'YEARLY'])->default('MONTHLY');
                $table->decimal('price', 14, 2)->nullable();
                $table->string('currency', 10)->default('VND');
                $table->boolean('is_active')->default(true);
                $table->text('description')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->index('tier', 'plans_tier_index');
                $table->index('db_mode', 'plans_db_mode_index');
                $table->index('is_active', 'plans_is_active_index');
            });
        }

        if (! Schema::hasTable('plan_module_access')) {
            Schema::create('plan_module_access', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('plan_id');
                $table->string('module_code', 100);
                $table->boolean('is_enabled')->default(true);
                $table->integer('limit_value')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->foreign('plan_id', 'plan_module_access_plan_id_fk')->references('id')->on('plans')->cascadeOnDelete();
                $table->unique(['plan_id', 'module_code'], 'uk_plan_module_access');
                $table->index('module_code', 'plan_module_access_module_code_index');
                $table->index('is_enabled', 'plan_module_access_is_enabled_index');
            });
        }

        if (Schema::hasTable('subscriptions') && ! Schema::hasColumn('subscriptions', 'plan_id')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->unsignedInteger('plan_id')->nullable()->after('tenant_id');
                $table->index('plan_id', 'subscriptions_plan_id_index');
                $table->foreign('plan_id', 'subscriptions_plan_id_fk')->references('id')->on('plans')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'plan_id')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropForeign('subscriptions_plan_id_fk');
                $table->dropIndex('subscriptions_plan_id_index');
                $table->dropColumn('plan_id');
            });
        }

        Schema::dropIfExists('plan_module_access');
        Schema::dropIfExists('plans');
    }
};
