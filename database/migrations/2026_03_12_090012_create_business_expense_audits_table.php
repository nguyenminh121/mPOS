<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_expense_audits', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('expense_id');
            $table->string('action', 50);
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->text('change_note')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('expense_id')->references('id')->on('business_expenses')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->index('expense_id', 'business_expense_audits_expense_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_expense_audits');
    }
};
