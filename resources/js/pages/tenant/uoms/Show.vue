<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Pencil } from 'lucide-vue-next';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type UomDetail = {
	id: number;
	name: string;
	symbol?: string | null;
	base_uom_id?: number | null;
	base_uom_name?: string | null;
	base_uom_symbol?: string | null;
	is_active: boolean;
	derived_uoms_count: number;
	product_units_count: number;
	product_prices_count: number;
};

const { uom } = defineProps<{
	uom: UomDetail;
}>();
</script>

<template>
	<Head title="Chi tiết đơn vị tính" />
	<TenantLayout>
		<div class="mx-auto max-w-3xl space-y-6">
			<div class="flex items-center justify-between gap-3">
				<div class="flex items-center gap-3">
					<Link :href="tenantRoutes.uoms.index()" class="rounded-xl border border-border/70 bg-background p-2 transition hover:bg-muted/60">
						<ArrowLeft class="size-5" />
					</Link>
					<div>
						<h1 class="text-2xl font-semibold tracking-tight">Chi tiết đơn vị tính</h1>
						<p class="text-sm text-muted-foreground">Thông tin định nghĩa và trạng thái sử dụng UOM.</p>
					</div>
				</div>

				<Link :href="tenantRoutes.uoms.edit(uom.id)" class="inline-flex items-center gap-2 rounded-xl border border-border/70 bg-background px-3 py-2 text-sm transition hover:bg-muted/60">
					<Pencil class="size-4" />
					Sửa
				</Link>
			</div>

			<section class="rounded-2xl border border-border/70 bg-card p-5">
				<dl class="grid gap-4 sm:grid-cols-2">
					<div>
						<dt class="text-xs uppercase tracking-wide text-muted-foreground">Tên UOM</dt>
						<dd class="mt-1 text-base font-semibold">{{ uom.name }}</dd>
					</div>
					<div>
						<dt class="text-xs uppercase tracking-wide text-muted-foreground">Ký hiệu</dt>
						<dd class="mt-1 font-mono text-sm">{{ uom.symbol || '-' }}</dd>
					</div>
					<div>
						<dt class="text-xs uppercase tracking-wide text-muted-foreground">Đơn vị gốc</dt>
						<dd class="mt-1 text-sm">{{ uom.base_uom_name ? `${uom.base_uom_name}${uom.base_uom_symbol ? ` (${uom.base_uom_symbol})` : ''}` : 'Không có' }}</dd>
					</div>
					<div>
						<dt class="text-xs uppercase tracking-wide text-muted-foreground">Trạng thái</dt>
						<dd class="mt-1">
							<span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="uom.is_active ? 'bg-primary/15 text-primary' : 'bg-muted text-muted-foreground'">
								{{ uom.is_active ? 'Hoạt động' : 'Tắt' }}
							</span>
						</dd>
					</div>
					<div>
						<dt class="text-xs uppercase tracking-wide text-muted-foreground">UOM quy đổi phụ thuộc</dt>
						<dd class="mt-1 text-sm">{{ uom.derived_uoms_count }}</dd>
					</div>
					<div>
						<dt class="text-xs uppercase tracking-wide text-muted-foreground">Liên kết Product Unit</dt>
						<dd class="mt-1 text-sm">{{ uom.product_units_count }}</dd>
					</div>
					<div>
						<dt class="text-xs uppercase tracking-wide text-muted-foreground">Liên kết Product Price</dt>
						<dd class="mt-1 text-sm">{{ uom.product_prices_count }}</dd>
					</div>
				</dl>
			</section>
		</div>
	</TenantLayout>
</template>
