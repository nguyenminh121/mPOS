<?php

namespace App\Domain\Tenant\Categories\Controllers;

use App\Actions\Tenant\Category\CreateCategoryAction;
use App\Actions\Tenant\Category\DeleteCategoryAction;
use App\Actions\Tenant\Category\UpdateCategoryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCategoryRequest;
use App\Http\Requests\Catalog\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\Tenant\CategoryService;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly CreateCategoryAction $createCategoryAction,
        private readonly UpdateCategoryAction $updateCategoryAction,
        private readonly DeleteCategoryAction $deleteCategoryAction,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Category::class);

        $categories = $this->categoryService->listTree($request->user());

        return Inertia::render('tenant/categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Category::class);

        return Inertia::render('tenant/categories/Form', [
            'mode' => 'create',
            'category' => null,
            'parentOptions' => $this->categoryService->parentOptions($request->user()),
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Category::class);

        $category = $this->createCategoryAction->execute($request->user(), $request->validated());

        if (! $request->expectsJson()) {
            return redirect()->route('tenant.categories.index')->with('success', 'Tạo danh mục thành công.');
        }

        return response()->json([
            'message' => 'Tạo danh mục thành công.',
            'category' => $this->toPayload($category),
        ]);
    }

    public function show(Category $category): Response
    {
        $this->authorize('view', $category);

        $category->load(['parent:id,name', 'children:id,parent_id,name', 'products:id,category_id']);

        return Inertia::render('tenant/categories/Show', [
            'category' => $this->toPayload($category) + [
                'parent_name' => $category->parent?->name,
                'children_count' => $category->children->count(),
                'products_count' => $category->products->count(),
            ],
        ]);
    }

    public function edit(Request $request, Category $category): Response
    {
        $this->authorize('update', $category);

        return Inertia::render('tenant/categories/Form', [
            'mode' => 'edit',
            'category' => $this->toPayload($category),
            'parentOptions' => $this->categoryService->parentOptions($request->user(), $category),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $category);

        $updated = $this->updateCategoryAction->execute($request->user(), $category, $request->validated());

        if (! $request->expectsJson()) {
            return redirect()->route('tenant.categories.index')->with('success', 'Cập nhật danh mục thành công.');
        }

        return response()->json([
            'message' => 'Cập nhật danh mục thành công.',
            'category' => $this->toPayload($updated),
        ]);
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $this->deleteCategoryAction->execute($request->user(), $category);

        return redirect()->route('tenant.categories.index')->with('success', 'Xóa danh mục thành công.');
    }

    private function toPayload(Category $category): array
    {
        return [
            'id' => $category->id,
            'code' => $category->code,
            'name' => $category->name,
            'description' => $category->description,
            'parent_id' => $category->parent_id,
            'is_active' => (bool) $category->is_active,
        ];
    }
}
