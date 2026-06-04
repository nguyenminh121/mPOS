<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tax_export_invoice_details') && ! Schema::hasTable('e_invoice_details')) {
            Schema::rename('tax_export_invoice_details', 'e_invoice_details');
        }

        if (Schema::hasTable('tax_export_batches') && ! Schema::hasTable('e_invoice_batches')) {
            Schema::rename('tax_export_batches', 'e_invoice_batches');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('e_invoice_details') && ! Schema::hasTable('tax_export_invoice_details')) {
            Schema::rename('e_invoice_details', 'tax_export_invoice_details');
        }

        if (Schema::hasTable('e_invoice_batches') && ! Schema::hasTable('tax_export_batches')) {
            Schema::rename('e_invoice_batches', 'tax_export_batches');
        }
    }
};
