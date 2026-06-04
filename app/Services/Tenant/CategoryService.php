<?php

namespace App\Services\Tenant;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    public function listTree(User $user): Collection
    {
        $categories = Category::query()
            ->where('tenant_id', $user->tenant_id)
            ->with('parent:id,name')
            ->withCount(['children', 'products'])
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'description', 'parent_id', 'is_active']);

        return $this->flattenTree($categories);
    }

    public function parentOptions(User $user, ?Category $excluded = null): Collection
    {
        $categories = Category::query()
            ->where('tenant_id', $user->tenant_id)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'parent_id', 'is_active']);

        if ($excluded !== null) {
            $descendantIds = $this->descendantIds($categories, (int) $excluded->id);
            $forbidden = array_merge([(int) $excluded->id], $descendantIds);
            $categories = $categories->reject(fn (Category $category) => in_array((int) $category->id, $forbidden, true))->values();
        }

        return $this->flattenTree($categories)->map(fn (array $item) => [
            'id' => $item['id'],
            'code' => $item['code'],
            'name' => $item['name'],
            'parent_id' => $item['parent_id'],
            'is_active' => $item['is_active'],
            'tree_label' => $item['tree_label'],
        ])->values();
    }

    public function create(User $user, array $data): Category
    {
        $parentId = Arr::get($data, 'parent_id');
        if ($parentId !== null) {
            $this->assertParentBelongsToTenant($user, (int) $parentId);
        }

        return Category::query()->create([
            ...Arr::only($data, ['code', 'name', 'description', 'parent_id', 'is_active']),
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, Category $category, array $data): Category
    {
        $this->guardTenant($user, $category);

        $parentId = Arr::get($data, 'parent_id');
        if ($parentId !== null) {
            if ((int) $parentId === (int) $category->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Danh mục cha không hợp lệ.',
                ]);
            }

            $this->assertParentBelongsToTenant($user, (int) $parentId);

            $categories = Category::query()
                ->where('tenant_id', $user->tenant_id)
                ->get(['id', 'parent_id']);

            $descendantIds = $this->descendantIds($categories, (int) $category->id);
            if (in_array((int) $parentId, $descendantIds, true)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Không thể chọn danh mục con làm danh mục cha.',
                ]);
            }
        }

        $category->fill(Arr::only($data, ['code', 'name', 'description', 'parent_id', 'is_active']));
        $category->save();

        return $category->refresh();
    }

    public function delete(User $user, Category $category): void
    {
        $this->guardTenant($user, $category);

        if ($category->children()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Không thể xóa danh mục đang có danh mục con.',
            ]);
        }

        $category->delete();
    }

    private function flattenTree(Collection $categories): Collection
    {
        $groupedByParent = $categories->groupBy(fn (Category $category) => $category->parent_id);

        $walk = function (?int $parentId, int $depth, array $path, array $visited = []) use (&$walk, $groupedByParent): array {
            $nodes = ($groupedByParent->get($parentId) ?? collect())
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();

            $result = [];

            foreach ($nodes as $node) {
                $nodeId = (int) $node->id;
                if (in_array($nodeId, $visited, true)) {
                    continue;
                }

                $nextPath = [...$path, (string) $node->name];

                $result[] = [
                    'id' => $nodeId,
                    'code' => $node->code,
                    'name' => $node->name,
                    'description' => $node->description,
                    'parent_id' => $node->parent_id,
                    'parent_name' => $node->parent?->name,
                    'is_active' => (bool) $node->is_active,
                    'children_count' => (int) ($node->children_count ?? 0),
                    'products_count' => (int) ($node->products_count ?? 0),
                    'depth' => $depth,
                    'tree_label' => str_repeat('— ', $depth) . $node->name,
                    'full_label' => implode(' / ', $nextPath),
                ];

                $result = [
                    ...$result,
                    ...$walk($nodeId, $depth + 1, $nextPath, [...$visited, $nodeId]),
                ];
            }

            return $result;
        };

        return collect($walk(null, 0, []))->values();
    }

    private function descendantIds(Collection $categories, int $rootId): array
    {
        $groupedByParent = $categories->groupBy(fn (Category $category) => $category->parent_id);
        $descendantIds = [];

        $collect = function (int $parentId) use (&$collect, &$descendantIds, $groupedByParent): void {
            $children = $groupedByParent->get($parentId) ?? collect();

            foreach ($children as $child) {
                $childId = (int) $child->id;
                if (in_array($childId, $descendantIds, true)) {
                    continue;
                }

                $descendantIds[] = $childId;
                $collect($childId);
            }
        };

        $collect($rootId);

        return $descendantIds;
    }

    private function assertParentBelongsToTenant(User $user, int $parentId): void
    {
        $exists = Category::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey($parentId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'parent_id' => 'Danh mục cha không tồn tại trong tenant.',
            ]);
        }
    }

    private function guardTenant(User $user, Category $category): void
    {
        if ($category->tenant_id !== $user->tenant_id) {
            throw ValidationException::withMessages([
                'category' => 'Category does not belong to this tenant.',
            ]);
        }
    }
}
