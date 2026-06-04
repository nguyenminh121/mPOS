<?php

namespace App\Policies;

use App\Models\PrinterConfig;
use App\Models\User;

/**
 * PrinterConfigPolicy — Printer / print station configuration.
 *
 * No SoftDeletes on PrinterConfig model.
 *
 * Role matrix:
 *  admin     : full CRUD
 *  manager   : read-only (network print overview)
 *  cashier   : read-only (needs to know which printer to use at their station)
 *  warehouse : read-only (label / packing slip printers)
 */
class PrinterConfigPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'warehouse']);
    }

    public function view(User $user, PrinterConfig $printerConfig): bool
    {
        return $this->isTenantMember($user, $printerConfig->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'cashier', 'warehouse']);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasRole('admin');
    }

    public function update(User $user, PrinterConfig $printerConfig): bool
    {
        return $this->isTenantMember($user, $printerConfig->tenant_id)
            && $user->hasRole('admin');
    }

    public function delete(User $user, PrinterConfig $printerConfig): bool
    {
        return $this->isTenantMember($user, $printerConfig->tenant_id)
            && $user->hasRole('admin');
    }

    // No restore/forceDelete — PrinterConfig does not use SoftDeletes.
}
