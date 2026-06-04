<?php

namespace App\Actions\Tenant\BusinessExpense;

use App\Models\BusinessExpense;
use App\Models\User;
use App\Services\Tenant\BusinessExpenseService;

class UpdateBusinessExpenseAction
{
    public function __construct(private readonly BusinessExpenseService $businessExpenseService)
    {
    }

    public function execute(User $user, BusinessExpense $expense, array $data): BusinessExpense
    {
        return $this->businessExpenseService->update($user, $expense, $data);
    }
}
