<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type CategoryRow = {
	id: number;
	code?: string | null;
	name: string;
	description?: string | null;
	parent_id?: number | null;
	parent_name?: string | null;
	is_active: boolean;
	children_count: number;
	products_count: number;
	depth: number;
	tree_label: string;
	full_label: string;
};

const props = defineProps<{
	categories: CategoryRow[];
}>();

const activeCount = computed(() => props.categories.filter((item) => item.is_active).length);

const deleteCategory = (id: number) => {
	if (!confirm('Bạn có chắc muốn xóa danh mục này?')) return;
	router.delete(tenantRoutes.categories.destroy(id));
};
</script>

<template>
	<Head title="Danh mục hàng hóa" />
	<TenantLayout>
		<div class="space-y-6">
			<section class="rounded-2xl border border-border/70 bg-card/95 p-6 shadow-sm">
				<div class="flex items-center justify-between gap-3">
					<div>
						<h1 class="text-2xl font-semibold tracking-tight">Danh mục hàng hóa</h1>
						<p class="mt-1 text-sm text-muted-foreground">Quản lý cây danh mục và mối quan hệ cha/con.</p>
					</div>
					<Link :href="tenantRoutes.categories.create()" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90">
						<Plus class="size-4" />
						Thêm danh mục
					</Link>
				</div>

				<div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Tổng danh mục</p>
						<p class="mt-1 text-2xl font-semibold">{{ categories.length }}</p>
					</div>
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Đang hoạt động</p>
						<p class="mt-1 text-2xl font-semibold">{{ activeCount }}</p>
					</div>
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Không hoạt động</p>
						<p class="mt-1 text-2xl font-semibold">{{ categories.length - activeCount }}</p>
					</div>
				</div>
			</section>

			<section class="overflow-hidden rounded-2xl border border-border/70 bg-card">
				<div class="overflow-x-auto">
					<table class="w-full text-sm">
						<thead class="bg-muted/40 text-left">
							<tr>
								<th class="p-3 font-medium">Mã</th>
								<th class="p-3 font-medium">Tên danh mục (tree)</th>
								<th class="p-3 font-medium">Danh mục cha</th>
								<th class="p-3 text-center font-medium">Con</th>
								<th class="p-3 text-center font-medium">Sản phẩm</th>
								<th class="p-3 text-center font-medium">Trạng thái</th>
								<th class="p-3 text-center font-medium">Thao tác</th>
							</tr>
						</thead>
						<tbody>
							<tr v-if="categories.length === 0">
								<td colspan="7" class="p-8 text-center text-muted-foreground">Chưa có danh mục nào.</td>
							</tr>
							<tr v-for="row in categories" :key="row.id" class="border-t hover:bg-muted/20">
								<td class="p-3 font-mono text-xs">{{ row.code || '-' }}</td>
								<td class="p-3">
									<p class="font-medium">{{ row.tree_label }}</p>
									<p class="text-xs text-muted-foreground">{{ row.full_label }}</p>
								</td>
								<td class="p-3 text-muted-foreground">{{ row.parent_name || '-' }}</td>
								<td class="p-3 text-center">{{ row.children_count }}</td>
								<td class="p-3 text-center">{{ row.products_count }}</td>
								<td class="p-3 text-center">
									<span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="row.is_active ? 'bg-primary/15 text-primary' : 'bg-muted text-muted-foreground'">
										{{ row.is_active ? 'Hoạt động' : 'Tắt' }}
									</span>
								</td>
								<td class="p-3">
									<div class="flex items-center justify-center">
										<DropdownMenu>
											<DropdownMenuTrigger as-child>
												<button type="button" class="inline-flex items-center justify-center rounded-md border border-border/70 p-1.5 hover:bg-muted/60" title="Thao tác">
													<MoreHorizontal class="size-3.5" />
												</button>
											</DropdownMenuTrigger>
											<DropdownMenuContent align="end" class="w-44">
												<DropdownMenuItem as-child>
													<Link :href="tenantRoutes.categories.show(row.id)" class="inline-flex w-full items-center gap-2">
														<Eye class="size-4" />
														Xem chi tiết
													</Link>
												</DropdownMenuItem>
												<DropdownMenuItem as-child>
													<Link :href="tenantRoutes.categories.edit(row.id)" class="inline-flex w-full items-center gap-2">
														<Pencil class="size-4" />
														Sửa
													</Link>
												</DropdownMenuItem>
												<DropdownMenuItem class="text-destructive focus:text-destructive" @click="deleteCategory(row.id)">
													<Trash2 class="size-4" />
													Xóa
												</DropdownMenuItem>
											</DropdownMenuContent>
										</DropdownMenu>
									</div>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</section>
		</div>
	</TenantLayout>
</template>
