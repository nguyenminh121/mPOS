<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_sequence_configs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('entity_type', 50);

            $table->string('prefix', 20);
            $table->string('separator', 5)->default('-');

            $table->string('date_format', 20)->nullable();
            $table->integer('sequence_length')->default(4);
            $table->enum('reset_period', ['DAILY', 'MONTHLY', 'YEARLY', 'NEVER'])->default('DAILY');

            $table->integer('current_value')->default(0);
            $table->date('last_reset_at')->nullable();

            $table->string('sample_output', 100)->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'entity_type'], 'uk_tenant_entity_sequence');
            $table->index('tenant_id', 'code_sequence_configs_tenant_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_sequence_configs');
    }
};
