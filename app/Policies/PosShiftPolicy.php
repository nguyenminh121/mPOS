<?php

namespace App\Policies;

use App\Models\PosShift;
use App\Models\User;

/**
 * PosShiftPolicy — POS shift (session) lifecycle management.
 *
 * A shift tracks cash drawer balances from open to close.
 * Cashiers own their shifts; admin/manager can see all shifts and intervene.
 *
 * No SoftDeletes on PosShift model.
 *
 * Role matrix:
 *  admin     : full access + forceClose (emergency closure of any shift)
 *  manager   : read all shifts, close/forceClose but not open
 *  cashier   : open (create) own shift, view own shift, close own shift
 *
 * Custom methods:
 *  open       : cashier may open a new shift
 *  close      : cashier may close own shift; admin/manager may close any shift
 *  forceClose : admin/manager only (emergency — bypasses normal close flow)
 */
class PosShiftPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'cashier']);
    }

    public function view(User $user, PosShift $posShift): bool
    {
        if (! $this->isTenantMember($user, $posShift->tenant_id)) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'manager'])) {
            return true;
        }

        // Cashier may only view their own shift.
        return $user->hasRole('cashier') && $posShift->user_id === $user->id;
    }

    /** Cashiers create shifts by opening them. */
    public function open(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasRole('cashier');
    }

    /** Alias for opening — Gate typically calls `create` for the store endpoint. */
    public function create(User $user): bool
    {
        return $this->open($user);
    }

    /**
     * Cashier may close their own open shift.
     * Admin and manager may close any shift.
     * A shift must be OPEN or SUSPENDED to be closeable.
     */
    public function close(User $user, PosShift $posShift): bool
    {
        if (! $this->isTenantMember($user, $posShift->tenant_id)) {
            return false;
        }

        if (! in_array($posShift->status, ['OPEN', 'SUSPENDED'], true)) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'manager'])) {
            return true;
        }

        return $user->hasRole('cashier') && $posShift->user_id === $user->id;
    }

    /** Emergency force-close — overrides any status check; admin/manager only. */
    public function forceClose(User $user, PosShift $posShift): bool
    {
        return $this->isTenantMember($user, $posShift->tenant_id)
            && $user->hasAnyRole(['admin', 'manager']);
    }

    /** Cashiers and managers may update their own shift summary details (OPEN/SUSPENDED only). */
    public function update(User $user, PosShift $posShift): bool
    {
        if (! $this->isTenantMember($user, $posShift->tenant_id)) {
            return false;
        }

        // A CLOSED shift is a finalised financial record — immutable.
        if ($posShift->status === 'CLOSED') {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'manager'])) {
            return true;
        }

        return $user->hasRole('cashier') && $posShift->user_id === $user->id;
    }

    // No delete — shifts are permanent financial records.
    // No restore/forceDelete — no SoftDeletes on PosShift.
}
