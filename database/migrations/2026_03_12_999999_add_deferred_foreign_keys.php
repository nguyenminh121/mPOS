<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DEFERRED FOREIGN KEYS
 *
 * Adds foreign key constraints that were deferred during table creation
 * because the referenced tables did not exist yet at that point.
 * Matches Section 12 of server.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        // product_storage → warehouse_locations (from 050014)
        Schema::table('product_storage', function (Blueprint $table) {
            $table->foreign('warehouse_location_id')->references('id')->on('warehouse_locations')->cascadeOnDelete();
        });

        // unit_price_history → import_receipts (from 050011)
        Schema::table('unit_price_history', function (Blueprint $table) {
            $table->foreign('import_receipt_id')->references('id')->on('import_receipts')->nullOnDelete();
        });

        // stocks → product_variants (from 060003)
        Schema::table('stocks', function (Blueprint $table) {
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
        });

        // stock_batches → product_variants, import_items (from 060004)
        Schema::table('stock_batches', function (Blueprint $table) {
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('import_item_id')->references('id')->on('import_items')->nullOnDelete();
        });

        // stock_movements → product_variants, orders, import_items, stock_takes (from 060007)
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('import_item_id')->references('id')->on('import_items')->nullOnDelete();
            $table->foreign('stock_take_id')->references('id')->on('stock_takes')->nullOnDelete();
        });

        // stock_take_items → product_variants (from 060009)
        Schema::table('stock_take_items', function (Blueprint $table) {
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
        });

        // purchase_order_items → product_variants (from 060011)
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
        });

        // import_items → product_variants (from 060013)
        Schema::table('import_items', function (Blueprint $table) {
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
        });

        // transfer_order_items → product_variants (from 060016)
        Schema::table('transfer_order_items', function (Blueprint $table) {
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
        });

        // orders → customers, invoices (from 070005)
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });

        // invoices → customers, tax_configs (from 080001)
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('tax_config_id')->references('id')->on('tax_configs')->nullOnDelete();
        });

        // return_orders → customers (from 080004)
        Schema::table('return_orders', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
        });

        // customers → customer_groups (from 090003)
        Schema::table('customers', function (Blueprint $table) {
            $table->foreign('group_id')->references('id')->on('customer_groups')->nullOnDelete();
        });

        // account_receivables → customers, payments (from 090009)
        Schema::table('account_receivables', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
        });

        // account_payables → payments (from 090010)
        Schema::table('account_payables', function (Blueprint $table) {
            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_payables', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
        });

        Schema::table('account_receivables', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['payment_id']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
        });

        Schema::table('return_orders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['tax_config_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['invoice_id']);
        });

        Schema::table('transfer_order_items', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
        });

        Schema::table('import_items', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
        });

        Schema::table('stock_take_items', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropForeign(['order_id']);
            $table->dropForeign(['import_item_id']);
            $table->dropForeign(['stock_take_id']);
        });

        Schema::table('stock_batches', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropForeign(['import_item_id']);
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
        });

        Schema::table('unit_price_history', function (Blueprint $table) {
            $table->dropForeign(['import_receipt_id']);
        });

        Schema::table('product_storage', function (Blueprint $table) {
            $table->dropForeign(['warehouse_location_id']);
        });
    }
};
