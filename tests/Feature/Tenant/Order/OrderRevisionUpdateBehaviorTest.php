<?php

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Tenant\OrderService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

test('order service creates revision instead of mutating original order directly', function () {
    DB::table('tenants')->insert([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'name' => 'Tenant Order Revision',
        'domain' => 'tenant-order-revision.local',
        'email' => 'tenant-order-revision@example.com',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tenantId = (int) DB::table('tenants')->where('domain', 'tenant-order-revision.local')->value('id');

    $user = User::factory()->create([
        'tenant_id' => $tenantId,
        'email_verified_at' => now(),
    ]);

    Role::findOrCreate('admin', 'web');
    $user->assignRole('admin');

    $warehouse = Warehouse::query()->create([
        'tenant_id' => $tenantId,
        'name' => 'Kho Revision',
        'code' => 'KHO-REVISION',
        'is_active' => true,
        'is_default' => true,
    ]);

    $customerId = (int) DB::table('customers')->insertGetId([
        'tenant_id' => $tenantId,
        'code' => 'CUS-REVISION',
        'name' => 'Revision Customer',
        'type' => 'INDIVIDUAL',
        'is_active' => true,
        'created_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $order = Order::query()->create([
        'tenant_id' => $tenantId,
        'code' => 'ORD-REV-001',
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
        'paid_amount' => 0,
        'debt_amount' => 100000,
    ]);

    $invoice = Invoice::query()->create([
        'tenant_id' => $tenantId,
        'code' => 'INV-REV-001',
        'order_id' => $order->id,
        'customer_id' => $customerId,
        'subtotal' => 100000,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total_amount' => 100000,
        'status' => 'ISSUED',
        'sale_at' => now(),
        'created_by' => $user->id,
    ]);

    $order->invoice_id = $invoice->id;
    $order->save();

    $updatedOrder = app(OrderService::class)->update($user, $order, [
        'note' => 'Updated by revision flow',
    ]);

    $order->refresh();
    $invoice->refresh();

    expect((int) $updatedOrder->id)->not->toBe((int) $order->id)
        ->and((int) $updatedOrder->parent_order_id)->toBe((int) $order->id)
        ->and((string) $updatedOrder->note)->toBe('Updated by revision flow')
        ->and((int) $updatedOrder->invoice_id)->toBe((int) $invoice->id)
        ->and($order->invoice_id)->toBeNull()
        ->and((bool) $order->is_locked)->toBeTrue()
        ->and((int) $invoice->order_id)->toBe((int) $updatedOrder->id);
});
