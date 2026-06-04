<?php

namespace App\Policies;

use App\Models\Stock;
use App\Models\User;

/**
 * StockPolicy — Current inventory level visibility.
 *
 * Stock records are system-managed. They are auto-updated by StockMovements
 * generated from orders, import receipts, stock takes, and transfers.
 * No create/update/delete is exposed at the application policy level.
 *
 * Role matrix (read-only):
 *  admin, manager, warehouse, accountant, viewer
 *
 * Note: cashiers do NOT see raw stock levels by design.
 * They see product availability (is_active + derived availability) through
 * product queries — not the underlying Stock table directly.
 *
 * Tenant isolation note: Stock has no direct tenant_id column.
 * stancl/tenancy's per-tenant DB connection provides infrastructure-level
 * isolation. The $user->tenant_id !== null guard ensures only tenant users
 * (not central platform users) reach this data; platform-admin is already
 * bypassed via Gate::before.
 */
class StockPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'warehouse', 'accountant', 'viewer']);
    }

    public function view(User $user, Stock $stock): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'warehouse', 'accountant', 'viewer']);
    }
}
