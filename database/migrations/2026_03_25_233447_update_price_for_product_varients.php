<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('price', 15, 2)->nullable()->default(0)->change();
            $table->decimal('cost_price', 15, 2)->nullable()->default(0)->change();
            $table->decimal('wholesale_price', 15, 2)->nullable()->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('product_variants')
            ->whereNull('price')
            ->update(['price' => 0]);

        DB::table('product_variants')
            ->whereNull('cost_price')
            ->update(['cost_price' => 0]);

        DB::table('product_variants')
            ->whereNull('wholesale_price')
            ->update(['wholesale_price' => 0]);

        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('price', 15, 2)->nullable(false)->default(0)->change();
            $table->decimal('cost_price', 15, 2)->nullable(false)->default(0)->change();
            $table->decimal('wholesale_price', 15, 2)->nullable(false)->default(0)->change();
        });
    }
};
