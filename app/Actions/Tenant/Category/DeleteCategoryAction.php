<?php

namespace App\Actions\Tenant\Category;

use App\Models\Category;
use App\Models\User;
use App\Services\Tenant\CategoryService;

class DeleteCategoryAction
{
    public function __construct(private readonly CategoryService $categoryService)
    {
    }

    public function execute(User $user, Category $category): void
    {
        $this->categoryService->delete($user, $category);
    }
}
