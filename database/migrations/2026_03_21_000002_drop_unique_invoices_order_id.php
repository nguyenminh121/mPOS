<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->indexExists('invoices', 'invoices_order_id_unique')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_order_id_unique');
        });
    }

    public function down(): void
    {
        if ($this->indexExists('invoices', 'invoices_order_id_unique')) {
            return;
        }

        if ($this->hasDuplicateOrderIds()) {
            $this->normalizeDuplicateOrderIds();
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->unique('order_id', 'invoices_order_id_unique');
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $tableName)
            ->where('index_name', $indexName)
            ->exists();
    }

    private function hasDuplicateOrderIds(): bool
    {
        return DB::table('invoices')
            ->whereNotNull('order_id')
            ->groupBy('order_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }

    private function normalizeDuplicateOrderIds(): void
    {
        DB::transaction(function (): void {
            $duplicateOrderIds = DB::table('invoices')
                ->whereNotNull('order_id')
                ->groupBy('order_id')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('order_id');

            foreach ($duplicateOrderIds as $orderId) {
                $ids = DB::table('invoices')
                    ->where('order_id', $orderId)
                    ->orderByDesc('id')
                    ->pluck('id')
                    ->values();

                $keepId = $ids->first();
                if ($keepId === null) {
                    continue;
                }

                DB::table('invoices')
                    ->where('order_id', $orderId)
                    ->where('id', '!=', $keepId)
                    ->update(['order_id' => null]);
            }
        });
    }
};
