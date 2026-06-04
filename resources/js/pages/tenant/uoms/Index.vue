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

type UomRow = {
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

const props = defineProps<{
	uoms: UomRow[];
}>();

const activeCount = computed(() => props.uoms.filter((item) => item.is_active).length);

const deleteUom = (id: number) => {
	if (!confirm('Bạn có chắc muốn xóa đơn vị tính này?')) return;
	router.delete(tenantRoutes.uoms.destroy(id));
};
</script>

<template>
	<Head title="Đơn vị tính" />
	<TenantLayout>
		<div class="space-y-6">
			<section class="rounded-2xl border border-border/70 bg-card/95 p-6 shadow-sm">
				<div class="flex items-center justify-between gap-3">
					<div>
						<h1 class="text-2xl font-semibold tracking-tight">Đơn vị tính (UOM)</h1>
						<p class="mt-1 text-sm text-muted-foreground">Quản lý đơn vị tính sản phẩm theo tenant.</p>
					</div>
					<Link :href="tenantRoutes.uoms.create()" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90">
						<Plus class="size-4" />
						Thêm UOM
					</Link>
				</div>

				<div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Tổng UOM</p>
						<p class="mt-1 text-2xl font-semibold">{{ uoms.length }}</p>
					</div>
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Đang hoạt động</p>
						<p class="mt-1 text-2xl font-semibold">{{ activeCount }}</p>
					</div>
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Không hoạt động</p>
						<p class="mt-1 text-2xl font-semibold">{{ uoms.length - activeCount }}</p>
					</div>
				</div>
			</section>

			<section class="overflow-hidden rounded-2xl border border-border/70 bg-card">
				<div class="overflow-x-auto">
					<table class="w-full text-sm">
						<thead class="bg-muted/40 text-left">
							<tr>
								<th class="p-3 font-medium">Tên UOM</th>
								<th class="p-3 font-medium">Ký hiệu</th>
								<th class="p-3 font-medium">Đơn vị gốc</th>
								<th class="p-3 text-center font-medium">Liên kết</th>
								<th class="p-3 text-center font-medium">Trạng thái</th>
								<th class="p-3 text-center font-medium">Thao tác</th>
							</tr>
						</thead>
						<tbody>
							<tr v-if="uoms.length === 0">
								<td colspan="6" class="p-8 text-center text-muted-foreground">Chưa có đơn vị tính nào.</td>
							</tr>
							<tr v-for="row in uoms" :key="row.id" class="border-t hover:bg-muted/20">
								<td class="p-3">
									<p class="font-semibold">{{ row.name }}</p>
								</td>
								<td class="p-3 font-mono text-xs">{{ row.symbol || '-' }}</td>
								<td class="p-3 text-muted-foreground">
									{{ row.base_uom_name ? `${row.base_uom_name}${row.base_uom_symbol ? ` (${row.base_uom_symbol})` : ''}` : 'Không có' }}
								</td>
								<td class="p-3 text-center text-xs text-muted-foreground">
									{{ row.derived_uoms_count }} quy đổi · {{ row.product_units_count + row.product_prices_count }} sử dụng
								</td>
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
													<Link :href="tenantRoutes.uoms.show(row.id)" class="inline-flex w-full items-center gap-2">
														<Eye class="size-4" />
														Xem chi tiết
													</Link>
												</DropdownMenuItem>
												<DropdownMenuItem as-child>
													<Link :href="tenantRoutes.uoms.edit(row.id)" class="inline-flex w-full items-center gap-2">
														<Pencil class="size-4" />
														Sửa
													</Link>
												</DropdownMenuItem>
												<DropdownMenuItem class="text-destructive focus:text-destructive" @click="deleteUom(row.id)">
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
