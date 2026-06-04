<?php

namespace App\Actions\Tenant\Product;

use App\Models\Product;
use App\Models\User;
use App\Services\Tenant\ProductService;

class UpdateProductAction
{
    public function __construct(private readonly ProductService $productService)
    {
    }

    public function execute(User $user, Product $product, array $data): Product
    {
        return $this->productService->update($user, $product, $data);
    }
}
