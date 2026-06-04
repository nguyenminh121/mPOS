<?php

declare(strict_types=1);

use App\Domain\Tenant\Invoices\Controllers\InvoiceController;
use App\Domain\Tenant\Stocks\Controllers\ImportReceiptController;
use App\Domain\Tenant\Configs\Controllers\InvoicePolicyConfigController;
use App\Domain\Tenant\BusinessExpenses\Controllers\BusinessExpenseController;
use App\Domain\Tenant\AuditLogs\Controllers\AuditLogController;
use App\Domain\Tenant\Categories\Controllers\CategoryController;
use App\Domain\Tenant\Customers\Controllers\CustomerController;
use App\Domain\Tenant\Customers\Controllers\CustomerAddressController;
use App\Domain\Tenant\Customers\Controllers\CustomerDebtExportController;
use App\Domain\Tenant\Customers\Controllers\CustomerGroupController;
use App\Domain\Tenant\Orders\Controllers\OrderController;
use App\Domain\Tenant\Payments\Controllers\PaymentController;
use App\Domain\Tenant\Payments\Controllers\PaymentMethodConfigController;
use App\Domain\Tenant\PriceLists\Controllers\PriceListController;
use App\Domain\Tenant\Products\Controllers\ProductController;
use App\Domain\Tenant\Returns\Controllers\ReturnOrderController;
use App\Domain\Tenant\Sales\Controllers\SalesController;
use App\Domain\Tenant\Stocks\Controllers\StockController;
use App\Domain\Tenant\Stocks\Controllers\StockPolicyConfigController;
use App\Domain\Tenant\Stocks\Controllers\StockTakeController;
use App\Domain\Tenant\Surcharges\Controllers\SurchargeCatalogController;
use App\Domain\Tenant\Configs\Controllers\TenantConfigController;
use App\Domain\Tenant\Invoices\Controllers\EInvoiceController;
use App\Domain\Tenant\Uoms\Controllers\UomController;
use App\Domain\Tenant\Stocks\Controllers\WarehouseController;
use App\Domain\Tenant\Stocks\Controllers\WarehouseLocationController;
use App\Http\Middleware\EnsurePosModeMiddleware;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

$tenantMiddleware = [
    'web',
];

if (! app()->environment('local')) {
    $tenantMiddleware[] = InitializeTenancyByDomain::class;
    $tenantMiddleware[] = PreventAccessFromCentralDomains::class;
}

Route::middleware($tenantMiddleware)->group(function () {
    Route::middleware(['auth', 'verified'])->prefix('tenant')->name('tenant.')->group(function () {
        Route::resource('categories', CategoryController::class);
        Route::resource('uoms', UomController::class);
        Route::resource('warehouses', WarehouseController::class);
        Route::resource('warehouse-locations', WarehouseLocationController::class);
        Route::resource('import-receipts', ImportReceiptController::class);
        Route::resource('stocks', StockController::class)->only(['index', 'show']);
        Route::post('stock-takes/{stockTake}/complete', [StockTakeController::class, 'complete'])->name('stock-takes.complete');
        Route::post('stock-takes/{stockTake}/undo', [StockTakeController::class, 'undo'])->name('stock-takes.undo');
        Route::resource('stock-takes', StockTakeController::class);

        Route::get('products/generate-code', [ProductController::class, 'generateCode'])->name('products.generate-code');
        Route::post('products/check-existing', [ProductController::class, 'checkExisting'])->name('products.check-existing');
        Route::post('products/import-bulk', [ProductController::class, 'importBulk'])->name('products.import-bulk');
        Route::post('products/store-batch', [ProductController::class, 'storeBatch'])->name('products.store-batch');
        Route::resource('products', ProductController::class)->parameters([
            'products' => 'productRef',
        ]);
        Route::resource('customer-groups', CustomerGroupController::class);
        Route::resource('customers', CustomerController::class);
        Route::post('customers/{customer}/addresses', [CustomerAddressController::class, 'store'])->name('customers.addresses.store');
        Route::put('customers/{customer}/addresses/{address}', [CustomerAddressController::class, 'update'])->name('customers.addresses.update');
        Route::delete('customers/{customer}/addresses/{address}', [CustomerAddressController::class, 'destroy'])->name('customers.addresses.destroy');
        Route::get('customers/{customer}/debt-export', CustomerDebtExportController::class)->name('customers.debt-export');
        Route::resource('price-lists', PriceListController::class);
        Route::resource('payment-method-configs', PaymentMethodConfigController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('payments/batch-debt', [PaymentController::class, 'storeBatchDebtPayment'])->name('payments.batch-debt');
        Route::resource('payments', PaymentController::class)->only(['store']);
        Route::resource('orders', OrderController::class)->parameters([
            'orders' => 'orderRef',
        ]);
        Route::resource('invoices', InvoiceController::class)->only(['index', 'show', 'destroy'])->parameters([
            'invoices' => 'invoiceRef',
        ]);
        Route::resource('e-invoices', EInvoiceController::class)
            ->only(['index', 'store', 'show']);
        Route::resource('returns', ReturnOrderController::class);
        Route::resource('surcharges', SurchargeCatalogController::class);
        Route::resource('business-expenses', BusinessExpenseController::class);
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');

        Route::get('sales', [SalesController::class, 'index'])->name('sales.index');
        Route::post('sales', [SalesController::class, 'store'])->name('sales.store');
        Route::post('sales/preorder', [SalesController::class, 'storePreorder'])
            ->middleware(EnsurePosModeMiddleware::class.':preorder')
            ->name('sales.preorder');
        Route::post('sales/return', [SalesController::class, 'storeReturn'])
            ->middleware(EnsurePosModeMiddleware::class.':return')
            ->name('sales.return');

        Route::get('config/code-sequences', [TenantConfigController::class, 'index'])->name('config.index');
        Route::post('config/code-sequences', [TenantConfigController::class, 'store'])->name('config.store');
        Route::put('config/code-sequences/{config}', [TenantConfigController::class, 'update'])->name('config.update');
        Route::delete('config/code-sequences/{config}', [TenantConfigController::class, 'destroy'])->name('config.destroy');
        Route::put('config/stock-policy', [StockPolicyConfigController::class, 'update'])->name('config.stock-policy.update');
        Route::put('config/invoice-policy', [InvoicePolicyConfigController::class, 'update'])->name('config.invoice-policy.update');
    });
});
