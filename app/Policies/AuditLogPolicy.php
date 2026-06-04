<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

/**
 * AuditLogPolicy — System-wide audit trail access.
 *
 * Audit logs are immutable write-once records.
 * No SoftDeletes. $timestamps = false (entries use created_at only).
 * Read-only for all users; admin, manager, and accountant have access.
 *
 * Role matrix:
 *  admin     : read-only (full audit trail visibility)
 *  manager   : read-only (operational oversight — own team actions)
 *  accountant: read-only (financial change tracking for compliance)
 */
class AuditLogPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager', 'accountant']);
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $this->isTenantMember($user, $auditLog->tenant_id)
            && $user->hasAnyRole(['admin', 'manager', 'accountant']);
    }

    // Audit log is immutable — no create, update, delete, restore, or forceDelete.
}
