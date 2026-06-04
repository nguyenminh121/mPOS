<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SECTION 2 (cont.): audit_logs
     * SECTION 2b: tenant_invitations
     *
     * Runs after Spatie permission tables (tenant_invitations FK → roles.id)
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('user_id');
            $table->string('action', 50);
            $table->string('table_name', 100);
            $table->unsignedInteger('record_id')->nullable();
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('tenant_id', 'audit_logs_tenant_id_index');
            $table->index('user_id', 'audit_logs_user_id_index');
            $table->index('table_name', 'audit_logs_table_name_index');
            $table->index('record_id', 'audit_logs_record_id_index');
            $table->index('created_at', 'audit_logs_created_at_index');
        });

        Schema::create('tenant_invitations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('email');
            $table->unsignedBigInteger('role_id');
            $table->string('token', 64)->unique();
            $table->enum('status', ['PENDING', 'ACCEPTED', 'EXPIRED', 'REVOKED'])->default('PENDING');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->unsignedInteger('invited_by');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('invited_by')->references('id')->on('users')->onDelete('cascade');
            $table->index('tenant_id', 'tenant_invitations_tenant_id_index');
            $table->index('email', 'tenant_invitations_email_index');
            $table->index('token', 'tenant_invitations_token_index');
            $table->index('status', 'tenant_invitations_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_invitations');
        Schema::dropIfExists('audit_logs');
    }
};
