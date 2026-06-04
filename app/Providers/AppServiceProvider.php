<?php

namespace App\Providers;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\ApiKey;
use App\Models\Attribute;
use App\Models\AuditLog;
use App\Models\Billing;
use App\Models\BusinessExpense;
use App\Models\Category;
use App\Models\CodeSequenceConfig;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerGroup;
use App\Models\DocumentTemplate;
use App\Models\ImportReceipt;
use App\Models\InventoryCostingConfig;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethodConfig;
use App\Models\PosShift;
use App\Models\PriceList;
use App\Models\PrinterConfig;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PurchaseOrder;
use App\Models\ReturnOrder;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StockTake;
use App\Models\StorageLocation;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\SurchargeCatalog;
use App\Models\TaxConfig;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantProfile;
use App\Models\TransferOrder;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\Webhook;
use App\Policies\AccountPayablePolicy;
use App\Policies\AccountReceivablePolicy;
use App\Policies\ApiKeyPolicy;
use App\Policies\AttributePolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\BillingPolicy;
use App\Policies\BusinessExpensePolicy;
use App\Policies\CategoryPolicy;
use App\Policies\CodeSequenceConfigPolicy;
use App\Policies\CreditNotePolicy;
use App\Policies\CustomerGroupPolicy;
use App\Policies\CustomerAddressPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DocumentTemplatePolicy;
use App\Policies\ImportReceiptPolicy;
use App\Policies\InventoryCostingConfigPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentMethodConfigPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PosShiftPolicy;
use App\Policies\PriceListPolicy;
use App\Policies\PrinterConfigPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProductVariantPolicy;
use App\Policies\PromotionPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\ReturnOrderPolicy;
use App\Policies\RolePolicy;
use App\Policies\StockBatchPolicy;
use App\Policies\StockMovementPolicy;
use App\Policies\StockPolicy;
use App\Policies\StockTakePolicy;
use App\Policies\StorageLocationPolicy;
use App\Policies\SubscriptionPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\SurchargeCatalogPolicy;
use App\Policies\TaxConfigPolicy;
use App\Policies\TenantInvitationPolicy;
use App\Policies\TenantPolicy;
use App\Policies\TenantProfilePolicy;
use App\Policies\TransferOrderPolicy;
use App\Policies\UomPolicy;
use App\Policies\UserPolicy;
use App\Policies\WarehouseLocationPolicy;
use App\Policies\WarehousePolicy;
use App\Policies\WebhookPolicy;
use App\Services\Tenant\AuditLogService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePolicies();
        $this->configureAuditTracking();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Register authorization policies and Gate callbacks.
     *
     * Gate::before — platform-admin bypasses ALL gate / policy checks.
     * This is intentional: platform-admin (Publisher / DevOps) has full
     * platform-level access and is trusted at the infrastructure level.
     */
    protected function configurePolicies(): void
    {
        // ── platform-admin bypass ──────────────────────────────────────────
        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->hasRole('platform-admin')) {
                return true;
            }

            return null; // fall through to normal policy check
        });

        // ── Platform policies (central DB) ───────────────────────────────
        Gate::policy(Tenant::class,           TenantPolicy::class);
        Gate::policy(User::class,             UserPolicy::class);
        Gate::policy(TenantInvitation::class, TenantInvitationPolicy::class);
        Gate::policy(Subscription::class,     SubscriptionPolicy::class);
        Gate::policy(Billing::class,          BillingPolicy::class);
        Gate::policy(Role::class,             RolePolicy::class);

        // ── Tenant ERP policies (per-tenant DB) ──────────────────────────
        // Catalog & Sales
        Gate::policy(Product::class,        ProductPolicy::class);
        Gate::policy(ProductVariant::class, ProductVariantPolicy::class);
        Gate::policy(Category::class,       CategoryPolicy::class);
        Gate::policy(Uom::class,            UomPolicy::class);
        Gate::policy(Attribute::class,      AttributePolicy::class);
        Gate::policy(Supplier::class,       SupplierPolicy::class);
        Gate::policy(Order::class,          OrderPolicy::class);
        Gate::policy(Customer::class,       CustomerPolicy::class);
        Gate::policy(CustomerAddress::class, CustomerAddressPolicy::class);
        Gate::policy(CustomerGroup::class,  CustomerGroupPolicy::class);
        Gate::policy(Invoice::class,        InvoicePolicy::class);
        Gate::policy(Payment::class,        PaymentPolicy::class);
        Gate::policy(ReturnOrder::class,    ReturnOrderPolicy::class);
        // Warehouse & Purchasing
        Gate::policy(Warehouse::class,          WarehousePolicy::class);
        Gate::policy(Stock::class,              StockPolicy::class);
        Gate::policy(StockMovement::class,      StockMovementPolicy::class);
        Gate::policy(StockTake::class,          StockTakePolicy::class);
        Gate::policy(PurchaseOrder::class,      PurchaseOrderPolicy::class);
        Gate::policy(ImportReceipt::class,      ImportReceiptPolicy::class);
        Gate::policy(TransferOrder::class,      TransferOrderPolicy::class);
        Gate::policy(WarehouseLocation::class,  WarehouseLocationPolicy::class);
        Gate::policy(StorageLocation::class,    StorageLocationPolicy::class);
        Gate::policy(StockBatch::class,         StockBatchPolicy::class);
        Gate::policy(InventoryCostingConfig::class, InventoryCostingConfigPolicy::class);
        // Marketing & Pricing
        Gate::policy(Promotion::class,       PromotionPolicy::class);
        Gate::policy(SurchargeCatalog::class, SurchargeCatalogPolicy::class);
        Gate::policy(PriceList::class,        PriceListPolicy::class);
        // POS
        Gate::policy(PosShift::class, PosShiftPolicy::class);
        // Finance & Audit
        Gate::policy(CreditNote::class,         CreditNotePolicy::class);
        Gate::policy(BusinessExpense::class,    BusinessExpensePolicy::class);
        Gate::policy(AccountReceivable::class,  AccountReceivablePolicy::class);
        Gate::policy(AccountPayable::class,     AccountPayablePolicy::class);
        Gate::policy(AuditLog::class,           AuditLogPolicy::class);
        // Configuration
        Gate::policy(TaxConfig::class,              TaxConfigPolicy::class);
        Gate::policy(PaymentMethodConfig::class,    PaymentMethodConfigPolicy::class);
        Gate::policy(DocumentTemplate::class,       DocumentTemplatePolicy::class);
        Gate::policy(PrinterConfig::class,          PrinterConfigPolicy::class);
        Gate::policy(CodeSequenceConfig::class,     CodeSequenceConfigPolicy::class);
        Gate::policy(Webhook::class,                WebhookPolicy::class);
        Gate::policy(ApiKey::class,                 ApiKeyPolicy::class);
        Gate::policy(TenantProfile::class,          TenantProfilePolicy::class);
    }

    protected function configureAuditTracking(): void
    {
        if (! config('audit.auto.enabled', true)) {
            return;
        }

        Event::listen('eloquent.created: *', function (string $eventName, array $data): void {
            $this->handleAuditModelEvent('created', $data);
        });

        Event::listen('eloquent.updated: *', function (string $eventName, array $data): void {
            $this->handleAuditModelEvent('updated', $data);
        });

        Event::listen('eloquent.deleted: *', function (string $eventName, array $data): void {
            $this->handleAuditModelEvent('deleted', $data);
        });

        Event::listen('eloquent.restored: *', function (string $eventName, array $data): void {
            $this->handleAuditModelEvent('restored', $data);
        });
    }

    private function handleAuditModelEvent(string $event, array $data): void
    {
        $model = $data[0] ?? null;
        if (! $model instanceof Model) {
            return;
        }

        app(AuditLogService::class)->recordModelEvent($event, $model);
    }
}
