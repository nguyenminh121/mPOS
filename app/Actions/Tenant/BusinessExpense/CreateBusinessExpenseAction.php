<?php

namespace App\Actions\Tenant\BusinessExpense;

use App\Models\BusinessExpense;
use App\Models\User;
use App\Services\Tenant\BusinessExpenseService;

class CreateBusinessExpenseAction
{
    public function __construct(private readonly BusinessExpenseService $businessExpenseService)
    {
    }

    public function execute(User $user, array $data): BusinessExpense
    {
        return $this->businessExpenseService->create($user, $data);
    }
}
