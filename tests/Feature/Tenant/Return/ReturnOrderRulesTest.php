<?php

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Tenant\ReturnOrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

function seedReturnTestCatalog(int $tenantId): array
{
    $categoryId = (int) DB::table('categories')->insertGetId([
        'tenant_id' => $tenantId,
        'code' => 'CAT-RETURN',
        'name' => 'Return Category',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $productId = (int) DB::table('products')->insertGetId([
        'tenant_id' => $tenantId,
        'category_id' => $categoryId,
        'name' => 'Return Product',
        'base_unit' => 'Cai',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $variantId = (int) DB::table('product_variants')->insertGetId([
        'product_id' => $productId,
        'name' => 'Return Variant',
        'base_unit' => 'Cai',
        'price' => 100000,
        'cost_price' => 80000,
        'wholesale_price' => 90000,
        'currency' => 'VND',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$variantId];
}

function seedReturnTestCustomer(int $tenantId, int $userId): int
{
    return (int) DB::table('customers')->insertGetId([
        'tenant_id' => $tenantId,
        'code' => 'CUS-RETURN',
        'name' => 'Return Customer',
        'type' => 'INDIVIDUAL',
        'is_active' => true,
        'created_by' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('return order can only be created from completed order', function () {
    DB::table('tenants')->insert([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'name' => 'Tenant Return A',
        'domain' => 'tenant-return-a.local',
        'email' => 'tenant-return-a@example.com',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tenantId = (int) DB::table('tenants')->where('domain', 'tenant-return-a.local')->value('id');

    $user = User::factory()->create([
        'tenant_id' => $tenantId,
        'email_verified_at' => now(),
    ]);

    Role::findOrCreate('admin', 'web');
    $user->assignRole('admin');

    $warehouse = Warehouse::query()->create([
        'tenant_id' => $tenantId,
        'name' => 'Kho Return A',
        'code' => 'KHO-RETURN-A',
        'is_active' => true,
        'is_default' => true,
    ]);

    $customerId = seedReturnTestCustomer($tenantId, (int) $user->id);
    [$variantId] = seedReturnTestCatalog($tenantId);

    $order = Order::query()->create([
        'tenant_id' => $tenantId,
        'code' => 'ORD-RETURN-DRAFT',
        'customer_id' => $customerId,
        'user_id' => $user->id,
        'warehouse_id' => $warehouse->id,
        'type' => 'RETAIL',
        'status' => 'DRAFT',
        'is_locked' => false,
        'total_amount' => 100000,
        'discount' => 0,
        'surcharge' => 0,
        'final_amount' => 100000,
        'paid_amount' => 100000,
        'debt_amount' => 0,
    ]);

    $orderItemId = (int) DB::table('order_items')->insertGetId([
        'order_id' => $order->id,
        'product_variant_id' => $variantId,
        'unit_name' => 'Cai',
        'conversion_rate' => 1,
        'quantity' => 1,
        'returned_quantity' => 0,
        'price' => 100000,
        'cost_price' => 80000,
        'discount' => 0,
        'promo_discount' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 100000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(ReturnOrderService::class);

    expect(fn () => $service->create($user, [
        'order_id' => $order->id,
        'customer_id' => $customerId,
        'warehouse_id' => $warehouse->id,
        'type' => 'PARTIAL',
        'status' => 'PENDING',
        'items' => [
            [
                'product_variant_id' => $variantId,
                'order_item_id' => $orderItemId,
                'quantity' => 1,
                'price' => 100000,
                'refund_amount' => 100000,
            ],
        ],
    ]))->toThrow(ValidationException::class);
});

test('creating return order keeps current order and replaces invoice with remaining sold quantity', function () {
    DB::table('tenants')->insert([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'name' => 'Tenant Return B',
        'domain' => 'tenant-return-b.local',
        'email' => 'tenant-return-b@example.com',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tenantId = (int) DB::table('tenants')->where('domain', 'tenant-return-b.local')->value('id');

    $user = User::factory()->create([
        'tenant_id' => $tenantId,
        'email_verified_at' => now(),
    ]);

    Role::findOrCreate('admin', 'web');
    $user->assignRole('admin');

    $warehouse = Warehouse::query()->create([
        'tenant_id' => $tenantId,
        'name' => 'Kho Return B',
        'code' => 'KHO-RETURN-B',
        'is_active' => true,
        'is_default' => true,
    ]);

    $customerId = seedReturnTestCustomer($tenantId, (int) $user->id);
    [$variantId] = seedReturnTestCatalog($tenantId);

    $order = Order::query()->create([
        'tenant_id' => $tenantId,
        'code' => 'ORD-RETURN-COMPLETED',
        'customer_id' => $customerId,
        'user_id' => $user->id,
        'warehouse_id' => $warehouse->id,
        'type' => 'RETAIL',
        'status' => 'COMPLETED',
        'is_locked' => false,
        'total_amount' => 200000,
        'discount' => 0,
        'surcharge' => 0,
        'final_amount' => 200000,
        'paid_amount' => 200000,
        'debt_amount' => 0,
        'completed_at' => now(),
    ]);

    $orderItemId = (int) DB::table('order_items')->insertGetId([
        'order_id' => $order->id,
        'product_variant_id' => $variantId,
        'unit_name' => 'Cai',
        'conversion_rate' => 1,
        'quantity' => 2,
        'returned_quantity' => 0,
        'price' => 100000,
        'cost_price' => 80000,
        'discount' => 0,
        'promo_discount' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 200000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $invoice = Invoice::query()->create([
        'tenant_id' => $tenantId,
        'code' => 'INV-RETURN-001',
        'order_id' => $order->id,
        'customer_id' => $customerId,
        'subtotal' => 200000,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total_amount' => 200000,
        'status' => 'ISSUED',
        'sale_at' => now(),
        'created_by' => $user->id,
    ]);

    DB::table('invoice_items')->insert([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $variantId,
        'product_name' => 'Return Variant',
        'unit_name' => 'Cai',
        'quantity' => 2,
        'price' => 100000,
        'discount' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 200000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $order->invoice_id = $invoice->id;
    $order->save();

    $service = app(ReturnOrderService::class);
    $returnOrder = $service->create($user, [
        'order_id' => $order->id,
        'customer_id' => $customerId,
        'warehouse_id' => $warehouse->id,
        'type' => 'PARTIAL',
        'status' => 'PENDING',
        'items' => [
            [
                'product_variant_id' => $variantId,
                'order_item_id' => $orderItemId,
                'quantity' => 1,
                'price' => 100000,
                'refund_amount' => 100000,
            ],
        ],
    ]);

    $order->refresh();
    $invoice->refresh();

    $replacementInvoice = Invoice::query()
        ->where('tenant_id', $tenantId)
        ->where('order_id', $order->id)
        ->where('parent_invoice_id', $invoice->id)
        ->where('status', 'ISSUED')
        ->latest('id')
        ->first();

    $replacementInvoiceItem = DB::table('invoice_items')
        ->where('invoice_id', $replacementInvoice?->id)
        ->where('product_variant_id', $variantId)
        ->first();

    expect($returnOrder->id)->toBeGreaterThan(0)
        ->and((int) $order->invoice_id)->toBe((int) $replacementInvoice?->id)
        ->and((string) $invoice->status)->toBe('REPLACED')
        ->and($replacementInvoice)->not->toBeNull()
        ->and((float) ($replacementInvoiceItem->quantity ?? 0))->toBe(1.0)
        ->and((float) ($replacementInvoice?->subtotal ?? 0))->toBe(100000.0)
        ->and((float) ($replacementInvoice?->total_amount ?? 0))->toBe(100000.0);
});
