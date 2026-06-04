<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Pencil } from 'lucide-vue-next';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type CategoryDetail = {
	id: number;
	code?: string | null;
	name: string;
	description?: string | null;
	parent_id?: number | null;
	parent_name?: string | null;
	is_active: boolean;
	children_count: number;
	products_count: number;
};

const { category } = defineProps<{
	category: CategoryDetail;
}>();
</script>

<template>
	<Head title="Chi tiết danh mục" />
	<TenantLayout>
		<div class="mx-auto max-w-3xl space-y-6">
			<div class="flex items-center justify-between gap-3">
				<div class="flex items-center gap-3">
					<Link :href="tenantRoutes.categories.index()" class="rounded-xl border border-border/70 bg-background p-2 transition hover:bg-muted/60">
						<ArrowLeft class="size-5" />
					</Link>
					<div>
						<h1 class="text-2xl font-semibold tracking-tight">Chi tiết danh mục</h1>
						<p class="text-sm text-muted-foreground">Thông tin danh mục và quan hệ trong cây.</p>
					</div>
				</div>

				<Link :href="tenantRoutes.categories.edit(category.id)" class="inline-flex items-center gap-2 rounded-xl border border-border/70 bg-background px-3 py-2 text-sm transition hover:bg-muted/60">
					<Pencil class="size-4" />
					Sửa
				</Link>
			</div>

			<section class="rounded-2xl border border-border/70 bg-card p-5">
				<dl class="grid gap-4 sm:grid-cols-2">
					<div>
						<dt class="text-xs uppercase tracking-wide text-muted-foreground">Mã</dt>
						<dd class="mt-1 font-mono text-sm">{{ category.code || '-' }}</dd>
					</div>
					<div>
						<dt class="text-xs uppercase tracking-wide text-muted-foreground">Trạng thái</dt>
						<dd class="mt-1">
							<span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="category.is_active ? 'bg-primary/15 text-primary' : 'bg-muted text-muted-foreground'">
								{{ category.is_active ? 'Hoạt động' : 'Tắt' }}
							</span>
						</dd>
					</div>
					<div class="sm:col-span-2">
						<dt class="text-xs uppercase tracking-wide text-muted-foreground">Tên danh mục</dt>
						<dd class="mt-1 text-base font-semibold">{{ category.name }}</dd>
					</div>
					<div>
						<dt class="text-xs uppercase tracking-wide text-muted-foreground">Danh mục cha</dt>
						<dd class="mt-1 text-sm">{{ category.parent_name || 'Không có' }}</dd>
					</div>
					<div>
						<dt class="text-xs uppercase tracking-wide text-muted-foreground">Số danh mục con</dt>
						<dd class="mt-1 text-sm">{{ category.children_count }}</dd>
					</div>
					<div>
						<dt class="text-xs uppercase tracking-wide text-muted-foreground">Số sản phẩm gán</dt>
						<dd class="mt-1 text-sm">{{ category.products_count }}</dd>
					</div>
					<div class="sm:col-span-2">
						<dt class="text-xs uppercase tracking-wide text-muted-foreground">Mô tả</dt>
						<dd class="mt-1 whitespace-pre-wrap text-sm text-muted-foreground">{{ category.description || '—' }}</dd>
					</div>
				</dl>
			</section>
		</div>
	</TenantLayout>
</template>
