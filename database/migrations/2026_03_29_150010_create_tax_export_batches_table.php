<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_export_batches', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('code', 50)->unique();
            $table->date('export_date');
            $table->enum('mode', ['BATCH', 'REALTIME'])->default('BATCH');
            $table->enum('status', ['DRAFT', 'PROCESSING', 'EXPORTED', 'FAILED', 'CANCELLED'])->default('DRAFT');
            $table->string('provider', 120)->nullable();
            $table->string('cqt_reference', 120)->nullable();
            $table->unsignedInteger('total_invoices')->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->timestamp('exported_at')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->longText('payload_snapshot')->nullable();
            $table->longText('response_snapshot')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index('tenant_id', 'tax_export_batches_tenant_id_index');
            $table->index('export_date', 'tax_export_batches_export_date_index');
            $table->index('status', 'tax_export_batches_status_index');
            $table->index('mode', 'tax_export_batches_mode_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_export_batches');
    }
};
