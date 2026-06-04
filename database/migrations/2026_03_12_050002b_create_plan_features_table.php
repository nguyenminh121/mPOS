<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('subscription_id');
            $table->unsignedInteger('feature_id');
            $table->integer('limit_value')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();
            $table->foreign('feature_id')->references('id')->on('features')->cascadeOnDelete();
            $table->unique(['subscription_id', 'feature_id'], 'uk_plan_feature');
            $table->index('subscription_id', 'plan_features_subscription_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
