<?php

use App\Models\Order;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Tenant\OrderDomainService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

afterEach(function () {
    putenv('DOMAIN_SERVICE_ORDER_ENABLED');
});

test('order update endpoint routes to domain service when feature flag is enabled', function () {
    putenv('DOMAIN_SERVICE_ORDER_ENABLED=true');
    config()->set('state_machine.domain_services.order', true);

    DB::table('tenants')->insert([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'email' => 'tenant-a@example.com',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tenantId = (int) DB::table('tenants')->where('domain', 'tenant-a.local')->value('id');

    $user = User::factory()->create([
        'tenant_id' => $tenantId,
        'email_verified_at' => now(),
    ]);

    $warehouse = Warehouse::query()->create([
        'tenant_id' => $tenantId,
        'name' => 'Kho Tổng',
        'code' => 'KHO-TONG',
        'is_active' => true,
        'is_default' => true,
    ]);

    Role::findOrCreate('admin', 'web');
    $user->assignRole('admin');

    $order = Order::query()->create([
        'tenant_id' => $tenantId,
        'code' => 'ORD-FLAG-ON-001',
        'user_id' => $user->id,
        'warehouse_id' => $warehouse->id,
        'type' => 'RETAIL',
        'status' => 'DRAFT',
        'is_locked' => false,
        'total_amount' => 0,
        'discount' => 0,
        'surcharge' => 0,
        'final_amount' => 0,
        'paid_amount' => 0,
        'debt_amount' => 0,
    ]);

    $domainService = Mockery::mock(OrderDomainService::class);
    $domainService
        ->shouldReceive('update')
        ->once()
        ->with(
            $user,
            \Mockery::on(fn ($model) => $model instanceof Order && (int) $model->id === (int) $order->id),
            \Mockery::on(fn (array $payload) => ($payload['status'] ?? null) === 'DRAFT'),
        )
        ->andReturn($order);

    app()->instance(OrderDomainService::class, $domainService);

    $response = $this
        ->withoutMiddleware([InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class])
        ->actingAs($user)
        ->put(route('tenant.orders.update', $order->code), [
            'status' => 'DRAFT',
        ]);

    $response
        ->assertRedirect(route('tenant.orders.index'))
        ->assertSessionHas('success', 'Order updated successfully.');
});

test('order update endpoint keeps legacy path when domain service feature flag is disabled', function () {
    putenv('DOMAIN_SERVICE_ORDER_ENABLED=false');
    config()->set('state_machine.domain_services.order', false);

    DB::table('tenants')->insert([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'name' => 'Tenant B',
        'domain' => 'tenant-b.local',
        'email' => 'tenant-b@example.com',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tenantId = (int) DB::table('tenants')->where('domain', 'tenant-b.local')->value('id');

    $user = User::factory()->create([
        'tenant_id' => $tenantId,
        'email_verified_at' => now(),
    ]);

    $warehouse = Warehouse::query()->create([
        'tenant_id' => $tenantId,
        'name' => 'Kho Chính',
        'code' => 'KHO-CHINH',
        'is_active' => true,
        'is_default' => true,
    ]);

    Role::findOrCreate('admin', 'web');
    $user->assignRole('admin');

    $order = Order::query()->create([
        'tenant_id' => $tenantId,
        'code' => 'ORD-FLAG-OFF-001',
        'user_id' => $user->id,
        'warehouse_id' => $warehouse->id,
        'type' => 'RETAIL',
        'status' => 'DRAFT',
        'is_locked' => false,
        'total_amount' => 0,
        'discount' => 0,
        'surcharge' => 0,
        'final_amount' => 0,
        'paid_amount' => 0,
        'debt_amount' => 0,
    ]);

    $domainService = Mockery::mock(OrderDomainService::class);
    $domainService
        ->shouldReceive('update')
        ->never();

    app()->instance(OrderDomainService::class, $domainService);

    $response = $this
        ->withoutMiddleware([InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class])
        ->actingAs($user)
        ->put(route('tenant.orders.update', $order->code), [
            'status' => 'DRAFT',
            'note' => 'legacy-path-check',
        ]);

    $response
        ->assertRedirect(route('tenant.orders.index'))
        ->assertSessionHas('success', 'Order updated successfully.');
});
