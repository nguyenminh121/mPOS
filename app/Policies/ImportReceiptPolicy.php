<?php

namespace App\Policies;

use App\Models\ImportReceipt;
use App\Models\User;

/**
 * ImportReceiptPolicy — Goods receipt / stock intake management.
 *
 * Import receipts record the physical arrival of stock from suppliers.
 * They are linked to purchase orders (or created standalone) and
 * automatically generate StockMovement (IN) records upon completion.
 *
 * Role matrix:
 *  admin     : full access
 *  manager   : full CRUD (no hard delete)
 *  warehouse : create, read, update (primary operator at receiving dock)
 *  accountant: read-only (accounts payable / cost of goods)
 *  viewer    : read-only
 *
 * Business rules:
 *  - Completed receipts are immutable (stock has already been posted).
 *  - Delete restricted to draft/pending receipts only.
 */
class ImportReceiptPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'warehouse', 'accountant', 'viewer']);
    }

    public function view(User $user, ImportReceipt $importReceipt): bool
    {
        return $this->isTenantMember($user, $importReceipt->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'warehouse', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager', 'warehouse']);
    }

    /** Update allowed for all active receipts; business transition checks are enforced in service layer. */
    public function update(User $user, ImportReceipt $importReceipt): bool
    {
        $status = strtoupper((string) $importReceipt->status);

        return $this->isTenantMember($user, $importReceipt->tenant_id)
            && $status !== 'CANCELLED'
            && $user->hasAnyRole(['admin', 'manager', 'warehouse', 'accountant']);
    }

    /** Delete is authorized at policy level; detailed status rules are enforced in service layer. */
    public function delete(User $user, ImportReceipt $importReceipt): bool
    {
        return $this->isTenantMember($user, $importReceipt->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'warehouse']);
    }

    // restore() and forceDelete() omitted — ImportReceipt does not use SoftDeletes.
}
