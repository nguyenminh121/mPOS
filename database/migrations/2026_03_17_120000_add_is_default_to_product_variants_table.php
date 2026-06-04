<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_active');
            $table->index(['product_id', 'is_default'], 'product_variants_product_default_index');
        });

        $productIds = DB::table('product_variants')
            ->select('product_id')
            ->distinct()
            ->pluck('product_id');

        foreach ($productIds as $productId) {
            $defaultVariantId = DB::table('product_variants')
                ->where('product_id', $productId)
                ->orderByRaw('deleted_at IS NOT NULL')
                ->orderBy('id')
                ->value('id');

            if ($defaultVariantId === null) {
                continue;
            }

            DB::table('product_variants')
                ->where('id', $defaultVariantId)
                ->update(['is_default' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('product_variants_product_default_index');
            $table->dropColumn('is_default');
        });
    }
};
