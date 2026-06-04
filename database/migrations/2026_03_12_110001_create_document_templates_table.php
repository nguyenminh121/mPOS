<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('description')->nullable();

            // Template content
            $table->longText('body_html');
            $table->text('css')->nullable();

            // Page setup
            $table->string('paper_size', 20)->default('A4');
            $table->enum('orientation', ['PORTRAIT', 'LANDSCAPE'])->default('PORTRAIT');
            $table->decimal('margin_top', 5, 2)->default(10.00);
            $table->decimal('margin_bottom', 5, 2)->default(10.00);
            $table->decimal('margin_left', 5, 2)->default(10.00);
            $table->decimal('margin_right', 5, 2)->default(10.00);

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->unsignedInteger('created_by');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['tenant_id', 'code'], 'uk_tenant_template_code');
            $table->index('tenant_id', 'document_templates_tenant_id_index');
            $table->index('is_active', 'document_templates_is_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
