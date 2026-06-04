<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('credit_note_id');
            $table->unsignedInteger('product_variant_id');
            $table->integer('quantity');
            $table->decimal('price', 14, 2);
            $table->decimal('total', 14, 2);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('credit_note_id')->references('id')->on('credit_notes')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->index('credit_note_id', 'credit_note_items_credit_note_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_items');
    }
};
