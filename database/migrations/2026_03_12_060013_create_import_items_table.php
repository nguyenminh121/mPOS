<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('import_receipt_id');
            $table->unsignedInteger('product_variant_id');
            $table->string('unit_name', 50)->default('Cái');
            $table->integer('conversion_rate')->default(1);
            $table->integer('quantity');
            $table->integer('quantity_in_base');
            $table->decimal('import_price', 14, 2);
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->decimal('tax_amount', 14, 2)->default(0.00);
            $table->decimal('total', 14, 2);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('import_receipt_id')->references('id')->on('import_receipts')->cascadeOnDelete();
            // product_variant_id FK deferred
            $table->index('import_receipt_id', 'import_items_import_receipt_id_index');
            $table->index('product_variant_id', 'import_items_product_variant_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_items');
    }
};
