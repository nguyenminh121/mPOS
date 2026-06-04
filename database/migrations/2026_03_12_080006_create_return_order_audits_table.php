<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_order_audits', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('return_order_id');
            $table->string('action', 50);
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->text('change_note')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('return_order_id')->references('id')->on('return_orders')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->index('return_order_id', 'return_order_audits_return_order_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_order_audits');
    }
};
