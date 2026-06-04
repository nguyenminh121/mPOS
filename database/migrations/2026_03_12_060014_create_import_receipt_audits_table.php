<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_receipt_audits', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('import_receipt_id');
            $table->string('action', 50);
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->text('change_note')->nullable();
            $table->string('field_changed', 100)->nullable();
            $table->integer('items_affected')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('import_receipt_id')->references('id')->on('import_receipts')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->index('import_receipt_id', 'import_receipt_audits_import_receipt_id_index');
            $table->index('action', 'import_receipt_audits_action_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_receipt_audits');
    }
};
