<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedInteger('customer_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        $this->normalizeInvoiceCustomerIds();

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedInteger('customer_id')->nullable(false)->change();
        });
    }

    private function normalizeInvoiceCustomerIds(): void
    {
        $tenantFallbackCustomers = DB::table('customers')
            ->selectRaw('tenant_id, MIN(id) as customer_id')
            ->groupBy('tenant_id')
            ->pluck('customer_id', 'tenant_id');

        foreach ($tenantFallbackCustomers as $tenantId => $customerId) {
            DB::table('invoices')
                ->where('tenant_id', (int) $tenantId)
                ->where(function ($query) {
                    $query->whereNull('customer_id')->orWhere('customer_id', 0);
                })
                ->update(['customer_id' => (int) $customerId]);
        }

        $globalFallbackCustomerId = DB::table('customers')->min('id');
        if (is_numeric($globalFallbackCustomerId) && (int) $globalFallbackCustomerId > 0) {
            DB::table('invoices')
                ->where(function ($query) {
                    $query->whereNull('customer_id')->orWhere('customer_id', 0);
                })
                ->update(['customer_id' => (int) $globalFallbackCustomerId]);
        }

        DB::table('invoices')
            ->where(function ($query) {
                $query->whereNull('customer_id')->orWhere('customer_id', 0);
            })
            ->delete();
    }
};
