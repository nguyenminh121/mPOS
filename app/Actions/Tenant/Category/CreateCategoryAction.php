<?php

namespace App\Actions\Tenant\Category;

use App\Models\Category;
use App\Models\User;
use App\Services\Tenant\CategoryService;

class CreateCategoryAction
{
    public function __construct(private readonly CategoryService $categoryService)
    {
    }

    public function execute(User $user, array $data): Category
    {
        return $this->categoryService->create($user, $data);
    }
}
