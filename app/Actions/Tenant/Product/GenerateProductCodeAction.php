<?php

namespace App\Actions\Tenant\Product;

use App\Models\User;
use App\Services\Tenant\ProductCodeService;

class GenerateProductCodeAction
{
    public function __construct(private readonly ProductCodeService $productCodeService)
    {
    }

    public function execute(User $user, string $name): string
    {
        return $this->productCodeService->generateForName($user, $name);
    }
}
