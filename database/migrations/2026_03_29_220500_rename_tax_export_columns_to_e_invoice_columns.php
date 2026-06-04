<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isMySql = DB::getDriverName() === 'mysql';

        if (Schema::hasTable('invoices')) {
            if (Schema::hasColumn('invoices', 'tax_export_status') && ! Schema::hasColumn('invoices', 'e_invoice_status')) {
                if ($isMySql) {
                    DB::statement("ALTER TABLE `invoices` CHANGE `tax_export_status` `e_invoice_status` ENUM('NOT_EXPORTED','EXPORTED') NOT NULL DEFAULT 'NOT_EXPORTED'");
                } else {
                    Schema::table('invoices', function (Blueprint $table) {
                        $table->renameColumn('tax_export_status', 'e_invoice_status');
                    });
                }
            }

            if (Schema::hasColumn('invoices', 'tax_exported_at') && ! Schema::hasColumn('invoices', 'e_invoiced_at')) {
                if ($isMySql) {
                    DB::statement('ALTER TABLE `invoices` CHANGE `tax_exported_at` `e_invoiced_at` TIMESTAMP NULL');
                } else {
                    Schema::table('invoices', function (Blueprint $table) {
                        $table->renameColumn('tax_exported_at', 'e_invoiced_at');
                    });
                }
            }

            $this->replaceIndexName('invoices', 'invoices_tax_export_status_index', 'invoices_e_invoice_status_index', 'e_invoice_status');
            $this->replaceIndexName('invoices', 'invoices_tax_exported_at_index', 'invoices_e_invoiced_at_index', 'e_invoiced_at');
        }

        if (Schema::hasTable('e_invoice_details')) {
            if (Schema::hasColumn('e_invoice_details', 'tax_export_batch_id') && ! Schema::hasColumn('e_invoice_details', 'e_invoice_batch_id')) {
                $this->dropForeignKeysForColumn('e_invoice_details', 'tax_export_batch_id');
                $this->dropIndexIfExists('e_invoice_details', 'uk_tax_export_batch_invoice');

                Schema::table('e_invoice_details', function (Blueprint $table) {
                    $table->renameColumn('tax_export_batch_id', 'e_invoice_batch_id');
                });

                Schema::table('e_invoice_details', function (Blueprint $table) {
                    $table->foreign('e_invoice_batch_id', 'e_invoice_details_e_invoice_batch_id_foreign')
                        ->references('id')
                        ->on('e_invoice_batches')
                        ->cascadeOnDelete();

                    $table->unique(['e_invoice_batch_id', 'invoice_id'], 'uk_e_invoice_batch_invoice');
                });
            }
        }
    }

    public function down(): void
    {
        $isMySql = DB::getDriverName() === 'mysql';

        if (Schema::hasTable('e_invoice_details')) {
            if (Schema::hasColumn('e_invoice_details', 'e_invoice_batch_id') && ! Schema::hasColumn('e_invoice_details', 'tax_export_batch_id')) {
                $this->dropForeignKeysForColumn('e_invoice_details', 'e_invoice_batch_id');
                $this->dropIndexIfExists('e_invoice_details', 'uk_e_invoice_batch_invoice');

                Schema::table('e_invoice_details', function (Blueprint $table) {
                    $table->renameColumn('e_invoice_batch_id', 'tax_export_batch_id');
                });

                Schema::table('e_invoice_details', function (Blueprint $table) {
                    $table->foreign('tax_export_batch_id', 'e_invoice_details_tax_export_batch_id_foreign')
                        ->references('id')
                        ->on('e_invoice_batches')
                        ->cascadeOnDelete();

                    $table->unique(['tax_export_batch_id', 'invoice_id'], 'uk_tax_export_batch_invoice');
                });
            }
        }

        if (Schema::hasTable('invoices')) {
            if (Schema::hasColumn('invoices', 'e_invoice_status') && ! Schema::hasColumn('invoices', 'tax_export_status')) {
                if ($isMySql) {
                    DB::statement("ALTER TABLE `invoices` CHANGE `e_invoice_status` `tax_export_status` ENUM('NOT_EXPORTED','EXPORTED') NOT NULL DEFAULT 'NOT_EXPORTED'");
                } else {
                    Schema::table('invoices', function (Blueprint $table) {
                        $table->renameColumn('e_invoice_status', 'tax_export_status');
                    });
                }
            }

            if (Schema::hasColumn('invoices', 'e_invoiced_at') && ! Schema::hasColumn('invoices', 'tax_exported_at')) {
                if ($isMySql) {
                    DB::statement('ALTER TABLE `invoices` CHANGE `e_invoiced_at` `tax_exported_at` TIMESTAMP NULL');
                } else {
                    Schema::table('invoices', function (Blueprint $table) {
                        $table->renameColumn('e_invoiced_at', 'tax_exported_at');
                    });
                }
            }

            $this->replaceIndexName('invoices', 'invoices_e_invoice_status_index', 'invoices_tax_export_status_index', 'tax_export_status');
            $this->replaceIndexName('invoices', 'invoices_e_invoiced_at_index', 'invoices_tax_exported_at_index', 'tax_exported_at');
        }
    }

    private function replaceIndexName(string $table, string $from, string $to, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        if (! $this->indexExists($table, $to)) {
            Schema::table($table, function (Blueprint $table) use ($column, $to) {
                $table->index($column, $to);
            });
        }

        $this->dropIndexIfExists($table, $from);
    }

    private function dropForeignKeysForColumn(string $table, string $column): void
    {
        $driver = DB::getDriverName();
        if ($driver !== 'mysql') {
            return;
        }

        $database = DB::getDatabaseName();
        $keys = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$database, $table, $column],
        );

        foreach ($keys as $key) {
            $constraint = (string) ($key->CONSTRAINT_NAME ?? '');
            if ($constraint === '') {
                continue;
            }

            DB::statement(sprintf('ALTER TABLE `%s` DROP FOREIGN KEY `%s`', $table, $constraint));
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($index) {
            $table->dropIndex($index);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = DB::getDriverName();
        if ($driver !== 'mysql') {
            return false;
        }

        $database = DB::getDatabaseName();
        $result = DB::selectOne(
            'SELECT COUNT(*) AS aggregate FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$database, $table, $index],
        );

        return (int) ($result->aggregate ?? 0) > 0;
    }
};
