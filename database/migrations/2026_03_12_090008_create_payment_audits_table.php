<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_audits', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('payment_id');
            $table->string('action', 50);
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->text('change_note')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index('payment_id', 'payment_audits_payment_id_index');
            $table->index('created_at', 'payment_audits_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_audits');
    }
};
