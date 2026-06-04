<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printer_configs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('name', 255);
            $table->string('printer_name', 255);
            $table->enum('printer_type', ['THERMAL', 'LASER', 'INKJET', 'DOT_MATRIX'])->default('THERMAL');
            $table->enum('connection_type', ['USB', 'NETWORK', 'BLUETOOTH', 'SERIAL'])->default('USB');
            $table->string('ip_address', 45)->nullable();
            $table->integer('port')->nullable();

            $table->string('document_type', 50);
            $table->unsignedInteger('template_id')->nullable();

            $table->integer('paper_width')->nullable();
            $table->boolean('auto_cut')->default(true);
            $table->integer('copies')->default(1);

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('template_id')->references('id')->on('document_templates')->nullOnDelete();
            $table->index('tenant_id', 'printer_configs_tenant_id_index');
            $table->index('document_type', 'printer_configs_document_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printer_configs');
    }
};
