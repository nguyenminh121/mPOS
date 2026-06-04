<?php

namespace App\Policies;

use App\Models\DocumentTemplate;
use App\Models\User;

/**
 * DocumentTemplatePolicy — Invoice, receipt, and document template management.
 *
 * No SoftDeletes on DocumentTemplate model.
 *
 * Role matrix:
 *  admin     : full CRUD (template design + publish)
 *  manager   : read-only (preview templates without editing)
 */
class DocumentTemplatePolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function view(User $user, DocumentTemplate $documentTemplate): bool
    {
        return $this->isTenantMember($user, $documentTemplate->tenant_id)
            && $user->hasAnyRole(['admin', 'manager']);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasRole('admin');
    }

    public function update(User $user, DocumentTemplate $documentTemplate): bool
    {
        return $this->isTenantMember($user, $documentTemplate->tenant_id)
            && $user->hasRole('admin');
    }

    public function delete(User $user, DocumentTemplate $documentTemplate): bool
    {
        return $this->isTenantMember($user, $documentTemplate->tenant_id)
            && $user->hasRole('admin');
    }

    // No restore/forceDelete — DocumentTemplate does not use SoftDeletes.
}
