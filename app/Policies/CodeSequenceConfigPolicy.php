<?php

namespace App\Policies;

use App\Models\CodeSequenceConfig;
use App\Models\User;

/**
 * CodeSequenceConfigPolicy — Auto-increment document number sequence configuration.
 *
 * Code sequences define the format and current counter for invoice numbers,
 * order numbers, PO numbers, etc. Incorrect configuration can produce duplicate
 * document numbers, making this admin-only.
 *
 * No SoftDeletes on CodeSequenceConfig model.
 *
 * Role matrix:
 *  admin : full CRUD (sequence design + reset)
 */
class CodeSequenceConfigPolicy extends BaseTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasRole('admin');
    }

    public function view(User $user, CodeSequenceConfig $codeSequenceConfig): bool
    {
        return $this->isTenantMember($user, $codeSequenceConfig->tenant_id)
            && $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasRole('admin');
    }

    public function update(User $user, CodeSequenceConfig $codeSequenceConfig): bool
    {
        return $this->isTenantMember($user, $codeSequenceConfig->tenant_id)
            && $user->hasRole('admin');
    }

    public function delete(User $user, CodeSequenceConfig $codeSequenceConfig): bool
    {
        return $this->isTenantMember($user, $codeSequenceConfig->tenant_id)
            && $user->hasRole('admin');
    }

    // No restore/forceDelete — CodeSequenceConfig does not use SoftDeletes.
}
