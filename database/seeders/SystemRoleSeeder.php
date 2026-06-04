<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * SystemRoleSeeder — Seeds central-platform roles and permissions.
 *
 * Run on the CENTRAL database only (not tenant databases).
 * Idempotent: safe to run multiple times via firstOrCreate.
 *
 * Roles:
 *  platform-admin — Publisher / DevOps. Full platform bypass via Gate::before.
 *                No permissions need to be synced; Gate handles it.
 *  support     — Helpdesk / read-only platform access. Explicitly assigned
 *                read permissions so they can view (not modify) data.
 *
 * Permission naming: "{resource}.{action}" — maps 1:1 to Policy methods.
 */
class SystemRoleSeeder extends Seeder
{
    private const GUARD = 'web';

    private const PERMISSIONS = [
        // Tenant management
        'tenant.viewAny',
        'tenant.view',
        'tenant.create',
        'tenant.update',
        'tenant.delete',
        'tenant.restore',
        'tenant.forceDelete',
        'tenant.suspend',
        'tenant.reinstate',

        // User management
        'user.viewAny',
        'user.view',
        'user.create',
        'user.update',
        'user.delete',
        'user.forceDelete',
        'user.assignRole',
        'user.impersonate',

        // Tenant invitations
        'invitation.viewAny',
        'invitation.view',
        'invitation.create',
        'invitation.delete',
        'invitation.revoke',

        // Subscriptions
        'subscription.viewAny',
        'subscription.view',
        'subscription.create',
        'subscription.update',
        'subscription.delete',

        // Billing
        'billing.viewAny',
        'billing.view',
        'billing.create',
        'billing.update',
        'billing.delete',

        // Role management
        'role.viewAny',
        'role.view',
        'role.create',
        'role.update',
        'role.delete',
        'role.assign',
    ];

    /** Read-only permissions assigned to the support role. */
    private const SUPPORT_PERMISSIONS = [
        'tenant.viewAny',
        'tenant.view',
        'user.viewAny',
        'user.view',
        'invitation.viewAny',
        'invitation.view',
        'subscription.viewAny',
        'subscription.view',
        'billing.viewAny',
        'billing.view',
        'role.viewAny',
        'role.view',
    ];

    public function run(): void
    {
        // Reset Spatie's permission cache before seeding
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create all system permissions
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => self::GUARD]
            );
        }

        // ── platform-admin ─────────────────────────────────────────────────
        // Gate::before() gives platform-admin full access without needing
        // explicit permission sync. Role must exist for hasRole() to work.
        Role::firstOrCreate(
            ['name' => 'platform-admin', 'guard_name' => self::GUARD]
        );

        // ── support (helpdesk) ──────────────────────────────────────────
        $supportRole = Role::firstOrCreate(
            ['name' => 'support', 'guard_name' => self::GUARD]
        );
        $supportRole->syncPermissions(self::SUPPORT_PERMISSIONS);

        $this->command->info('System roles seeded: platform-admin, support');
        $this->command->info('Permissions created: ' . count(self::PERMISSIONS));
    }
}
