<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('e_invoice_status', ['NOT_EXPORTED', 'EXPORTED'])
                ->default('NOT_EXPORTED')
                ->after('status');
            $table->timestamp('e_invoiced_at')->nullable()->after('e_invoice_status');

            $table->index('e_invoice_status', 'invoices_e_invoice_status_index');
            $table->index('e_invoiced_at', 'invoices_e_invoiced_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_e_invoice_status_index');
            $table->dropIndex('invoices_e_invoiced_at_index');
            $table->dropColumn(['e_invoice_status', 'e_invoiced_at']);
        });
    }
};
