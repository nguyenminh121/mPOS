<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('warehouse_id');
            $table->string('code', 100);
            $table->string('name');
            $table->enum('type', ['AREA', 'FLOOR', 'AISLE', 'RACK', 'SHELF', 'BIN']);
            $table->unsignedInteger('parent_id')->nullable();
            $table->integer('capacity')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('warehouse_locations')->cascadeOnDelete();
            $table->unique(['warehouse_id', 'code'], 'uk_warehouse_location_code');
            $table->index('warehouse_id', 'warehouse_locations_warehouse_id_index');
            $table->index('parent_id', 'warehouse_locations_parent_id_index');
            $table->index('type', 'warehouse_locations_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_locations');
    }
};
