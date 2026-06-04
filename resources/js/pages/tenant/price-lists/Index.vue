<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type PriceListRow = {
	id: number;
	code: string;
	name: string;
	priority: number;
	items_count: number;
	customers_count: number;
	customer_groups_count: number;
	is_default: boolean;
	is_active: boolean;
	start_date?: string | null;
	end_date?: string | null;
};

const props = defineProps<{
	priceLists: PriceListRow[];
}>();

const activeCount = computed(() => props.priceLists.filter((item) => item.is_active).length);
const defaultCount = computed(() => props.priceLists.filter((item) => item.is_default).length);
const totalPriceRows = computed(() => props.priceLists.reduce((sum, item) => sum + item.items_count, 0));

const deletePriceList = (id: number) => {
	if (!confirm('Bạn có chắc muốn xóa bảng giá này?')) return;
	router.delete(tenantRoutes.priceLists.destroy(id));
};
</script>

<template>
	<Head title="Bảng giá" />
	<TenantLayout>
		<div class="space-y-6">
			<section class="rounded-2xl border border-border/70 bg-card/95 p-6 shadow-sm">
				<div class="flex items-center justify-between gap-3">
					<div>
						<h1 class="text-2xl font-semibold tracking-tight">Bảng giá</h1>
						<p class="mt-1 text-sm text-muted-foreground">Cấu hình giá bán riêng theo khách hàng, nhóm khách hàng, biến thể và UOM.</p>
					</div>
					<Button as-child>
						<Link :href="tenantRoutes.priceLists.create()">
							<Plus class="size-4" />
							Thêm bảng giá
						</Link>
					</Button>
				</div>

				<div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-4">
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Tổng bảng giá</p>
						<p class="mt-1 text-2xl font-semibold">{{ priceLists.length }}</p>
					</div>
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Bảng giá mặc định</p>
						<p class="mt-1 text-2xl font-semibold">{{ defaultCount }}</p>
					</div>
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Đang hoạt động</p>
						<p class="mt-1 text-2xl font-semibold">{{ activeCount }}</p>
					</div>
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Tổng dòng giá</p>
						<p class="mt-1 text-2xl font-semibold">{{ totalPriceRows }}</p>
					</div>
				</div>
			</section>

			<section class="overflow-hidden rounded-2xl border border-border/70 bg-card">
				<div class="overflow-x-auto">
					<table class="w-full text-sm">
						<thead class="bg-muted/40 text-left">
							<tr>
								<th class="p-3 font-medium">Mã</th>
								<th class="p-3 font-medium">Tên bảng giá</th>
								<th class="p-3 text-center font-medium">Ưu tiên</th>
								<th class="p-3 text-center font-medium">Số dòng giá</th>
								<th class="p-3 text-center font-medium">Đang dùng</th>
								<th class="p-3 text-center font-medium">Hiệu lực</th>
								<th class="p-3 text-center font-medium">Trạng thái</th>
								<th class="p-3 text-center font-medium">Thao tác</th>
							</tr>
						</thead>
						<tbody>
							<tr v-if="priceLists.length === 0">
								<td colspan="8" class="p-8 text-center text-muted-foreground">Chưa có bảng giá nào.</td>
							</tr>
							<tr v-for="row in priceLists" :key="row.id" class="border-t hover:bg-muted/20">
								<td class="p-3 font-mono text-xs">{{ row.code }}</td>
								<td class="p-3">
									<p class="font-semibold">{{ row.name }}</p>
									<div class="mt-1 flex gap-1">
										<Badge v-if="row.is_default" variant="outline">Mặc định</Badge>
									</div>
								</td>
								<td class="p-3 text-center">{{ row.priority }}</td>
								<td class="p-3 text-center">{{ row.items_count }}</td>
								<td class="p-3 text-center">{{ row.customers_count + row.customer_groups_count }}</td>
								<td class="p-3 text-center text-xs text-muted-foreground">{{ row.start_date || '-' }} → {{ row.end_date || '-' }}</td>
								<td class="p-3 text-center">
									<Badge :variant="row.is_active ? 'default' : 'secondary'">{{ row.is_active ? 'Hoạt động' : 'Tắt' }}</Badge>
								</td>
								<td class="p-3">
									<div class="flex items-center justify-center">
										<DropdownMenu>
											<DropdownMenuTrigger as-child>
												<Button variant="outline" size="icon-sm"><MoreHorizontal class="size-3.5" /></Button>
											</DropdownMenuTrigger>
											<DropdownMenuContent align="end" class="w-44">
												<DropdownMenuItem as-child><Link :href="tenantRoutes.priceLists.show(row.id)" class="inline-flex w-full items-center gap-2"><Eye class="size-4" />Xem</Link></DropdownMenuItem>
												<DropdownMenuItem as-child><Link :href="tenantRoutes.priceLists.edit(row.id)" class="inline-flex w-full items-center gap-2"><Pencil class="size-4" />Sửa</Link></DropdownMenuItem>
												<DropdownMenuItem class="text-destructive focus:text-destructive" @click="deletePriceList(row.id)"><Trash2 class="size-4" />Xóa</DropdownMenuItem>
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
