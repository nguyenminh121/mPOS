<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('user_id');
            $table->string('device_id', 255);
            $table->enum('sync_type', ['PUSH', 'PULL', 'FULL']);
            $table->string('table_name', 100);
            $table->integer('record_count')->default(0);
            $table->enum('status', ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'FAILED'])->default('PENDING');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('tenant_id', 'sync_logs_tenant_id_index');
            $table->index('device_id', 'sync_logs_device_id_index');
            $table->index('status', 'sync_logs_status_index');
            $table->index('created_at', 'sync_logs_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
