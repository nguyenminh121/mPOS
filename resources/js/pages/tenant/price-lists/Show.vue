<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Pencil } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

defineProps<{
	priceList: {
		id: number;
		code: string;
		name: string;
		is_default: boolean;
		is_active: boolean;
		priority: number;
		description?: string | null;
		start_date?: string | null;
		end_date?: string | null;
		items_count: number;
		customers_count: number;
		customer_groups_count: number;
		items: Array<{
			id: number;
			product_name?: string | null;
			variant_name?: string | null;
			variant_sku?: string | null;
			unit_name: string;
			price: number;
			min_quantity: number;
		}>;
	};
}>();
</script>

<template>
	<Head title="Chi tiết bảng giá" />
	<TenantLayout>
		<div class="mx-auto max-w-6xl space-y-6">
			<div class="flex items-center justify-between gap-3">
				<div class="flex items-center gap-3">
					<Button as-child variant="outline" size="icon">
						<Link :href="tenantRoutes.priceLists.index()"><ArrowLeft class="size-5" /></Link>
					</Button>
					<div>
						<h1 class="text-2xl font-semibold tracking-tight">{{ priceList.name }}</h1>
						<p class="text-sm text-muted-foreground">Mã: {{ priceList.code }} · Ưu tiên: {{ priceList.priority }}</p>
					</div>
				</div>
				<Button as-child variant="outline">
					<Link :href="tenantRoutes.priceLists.edit(priceList.id)"><Pencil class="size-4" />Sửa</Link>
				</Button>
			</div>

			<section class="rounded-2xl border border-border/70 bg-card p-5">
				<div class="mb-4 flex flex-wrap items-center gap-2">
					<Badge variant="outline">Ưu tiên: {{ priceList.priority }}</Badge>
					<Badge v-if="priceList.is_default" variant="outline">Mặc định</Badge>
					<Badge :variant="priceList.is_active ? 'default' : 'secondary'">{{ priceList.is_active ? 'Hoạt động' : 'Tắt' }}</Badge>
				</div>

				<div class="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
					<div><span class="text-muted-foreground">Hiệu lực:</span> {{ priceList.start_date || '-' }} → {{ priceList.end_date || '-' }}</div>
					<div><span class="text-muted-foreground">Số dòng giá:</span> {{ priceList.items_count }}</div>
					<div><span class="text-muted-foreground">Khách hàng áp dụng:</span> {{ priceList.customers_count }}</div>
					<div><span class="text-muted-foreground">Nhóm KH áp dụng:</span> {{ priceList.customer_groups_count }}</div>
					<div class="md:col-span-2"><span class="text-muted-foreground">Mô tả:</span> {{ priceList.description || '-' }}</div>
				</div>
			</section>

			<section class="overflow-hidden rounded-2xl border border-border/70 bg-card">
				<div class="overflow-x-auto">
					<table class="w-full text-sm">
						<thead class="bg-muted/40 text-left">
							<tr>
								<th class="p-3 font-medium">Sản phẩm</th>
								<th class="p-3 font-medium">Biến thể</th>
								<th class="p-3 font-medium">UOM</th>
								<th class="p-3 text-right font-medium">SL tối thiểu</th>
								<th class="p-3 text-right font-medium">Giá</th>
							</tr>
						</thead>
						<tbody>
							<tr v-if="priceList.items.length === 0">
								<td colspan="5" class="p-8 text-center text-muted-foreground">Bảng giá chưa có dòng giá nào.</td>
							</tr>
							<tr v-for="item in priceList.items" :key="item.id" class="border-t">
								<td class="p-3">{{ item.product_name || '-' }}</td>
								<td class="p-3">{{ item.variant_name || '-' }} <span class="text-xs text-muted-foreground">{{ item.variant_sku || '' }}</span></td>
								<td class="p-3">{{ item.unit_name }}</td>
								<td class="p-3 text-right">{{ item.min_quantity }}</td>
								<td class="p-3 text-right font-medium">{{ item.price.toLocaleString('vi-VN') }}</td>
							</tr>
						</tbody>
					</table>
				</div>
			</section>
		</div>
	</TenantLayout>
</template>
