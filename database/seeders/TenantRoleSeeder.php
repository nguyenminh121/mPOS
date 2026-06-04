<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * TenantRoleSeeder — Seeds per-tenant roles.
 *
 * Run INSIDE each tenant's database context (not the central DB).
 * Typically invoked via a TenantCreated event listener, or manually:
 *
 *   // Seed a specific tenant:
 *   tenancy()->initialize($tenant);
 *   (new TenantRoleSeeder)->run();
 *   tenancy()->end();
 *
 *   // Or via artisan for all tenants:
 *   php artisan tenants:run db:seed --option="class=TenantRoleSeeder"
 *
 * Idempotent: safe to re-run via firstOrCreate.
 *
 * Role hierarchy (highest → lowest):
 *  admin      — Full control of everything within the tenant.
 *               Manages users, roles, config, all ERP modules.
 *  manager    — Day-to-day operations. No destructive deletes or config changes.
 *  cashier    — POS-focused. Creates orders, collects payments, handles returns.
 *               Read-only on everything else.
 *  warehouse  — Stock operations. Manages receipts, transfers, stock takes.
 *               No access to sales, customers, or financials.
 *  accountant — Finance read-only. Views invoices, payments, costs, reports.
 *               May reconcile payments (update).
 *  viewer     — Passive read-only across all modules.
 */
class TenantRoleSeeder extends Seeder
{
    private const GUARD = 'web';

    private const ROLES = [
        'admin',
        'manager',
        'cashier',
        'warehouse',
        'accountant',
        'viewer',
    ];

    public function run(): void
    {
        // Reset Spatie's permission cache before seeding
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::ROLES as $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => self::GUARD]
            );
        }

        if ($this->command ?? null) {
            $this->command->info('Tenant roles seeded: ' . implode(', ', self::ROLES));
        }
    }
}
