<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_audits', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('invoice_id');
            $table->string('action', 50);
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index('invoice_id', 'invoice_audits_invoice_id_index');
            $table->index('created_at', 'invoice_audits_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_audits');
    }
};
