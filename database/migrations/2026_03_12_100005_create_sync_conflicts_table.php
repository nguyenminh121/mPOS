<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_conflicts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedBigInteger('sync_log_id');
            $table->string('table_name', 100);
            $table->integer('record_id');
            $table->json('local_data');
            $table->json('server_data');
            $table->enum('resolution', ['LOCAL_WINS', 'SERVER_WINS', 'MANUAL', 'UNRESOLVED'])->default('UNRESOLVED');
            $table->unsignedInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('sync_log_id')->references('id')->on('sync_logs')->cascadeOnDelete();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
            $table->index('tenant_id', 'sync_conflicts_tenant_id_index');
            $table->index('resolution', 'sync_conflicts_resolution_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_conflicts');
    }
};
