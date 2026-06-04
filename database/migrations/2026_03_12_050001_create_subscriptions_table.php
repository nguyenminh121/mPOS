<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('plan_name');
            $table->enum('status', ['active', 'canceled', 'past_due', 'trialing'])->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('trial_ends_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id', 'subscriptions_tenant_id_index');
            $table->index('status', 'subscriptions_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
