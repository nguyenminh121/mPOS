<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

/**
 * InvoicePolicy — Invoice / tax document management.
 *
 * Role matrix:
 *  admin     : full access + void
 *  manager   : read, update, void
 *  cashier   : read + create (auto-generated at checkout, can also do manually)
 *  accountant: read-only (primary accounting consumer)
 *  viewer    : read-only
 *
 * Business rules:
 *  - Invoices are voided (financial reversal), not deleted.
 *  - Voided invoices cannot be updated.
 *  - Soft-delete is admin-only for draft invoice cleanup.
 */
class InvoicePolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'accountant', 'viewer']);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->isTenantMember($user, $invoice->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'accountant', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager', 'cashier']);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->isTenantMember($user, $invoice->tenant_id)
            && $invoice->status !== 'voided'
            && $user->hasAnyRole(['admin', 'manager']);
    }

    /** Void an invoice — financial reversal, creates a credit note trail. */
    public function void(User $user, Invoice $invoice): bool
    {
        return $this->isTenantMember($user, $invoice->tenant_id)
            && $invoice->status !== 'voided'
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->isTenantMember($user, $invoice->tenant_id)
            && $user->hasRole('admin');
    }

    public function restore(User $user, Invoice $invoice): bool
    {
        return $this->isTenantMember($user, $invoice->tenant_id)
            && $user->hasRole('admin');
    }

    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return $this->isTenantMember($user, $invoice->tenant_id)
            && $user->hasRole('admin');
    }
}
