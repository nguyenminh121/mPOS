<?php

namespace App\Actions\Tenant\Product;

use App\Models\Product;
use App\Models\User;
use App\Services\Tenant\ProductService;

class CreateProductAction
{
    public function __construct(private readonly ProductService $productService)
    {
    }

    public function execute(User $user, array $data): Product
    {
        return $this->productService->create($user, $data);
    }
}
