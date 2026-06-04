<?php

namespace App\Actions\Tenant\Product;

use App\Models\Product;
use App\Models\User;
use App\Services\Tenant\ProductService;

class DeleteProductAction
{
    public function __construct(private readonly ProductService $productService)
    {
    }

    public function execute(User $user, Product $product): void
    {
        $this->productService->delete($user, $product);
    }
}
