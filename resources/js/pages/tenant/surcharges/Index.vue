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

type SurchargeRow = {
	id: number;
	code: string;
	name: string;
	description?: string | null;
	type: 'PERCENT' | 'FIXED_AMOUNT';
	value: number;
	is_auto_apply: boolean;
	is_refundable: boolean;
	is_active: boolean;
	category?: string | null;
	created_by_name?: string | null;
};

const props = defineProps<{
	surcharges: SurchargeRow[];
}>();

const activeCount = computed(() => props.surcharges.filter((item) => item.is_active).length);
const autoApplyCount = computed(() => props.surcharges.filter((item) => item.is_auto_apply).length);

const formatValue = (row: SurchargeRow): string => {
	if (row.type === 'PERCENT') {
		return `${row.value}%`;
	}

	return `${row.value.toLocaleString('vi-VN')} đ`;
};

const deleteSurcharge = (id: number) => {
	if (!confirm('Bạn có chắc muốn xóa chi phí phát sinh này?')) return;
	router.delete(tenantRoutes.surcharges.destroy(id));
};
</script>

<template>
	<Head title="Chi phí phát sinh" />
	<TenantLayout>
		<div class="space-y-6">
			<section class="rounded-2xl border border-border/70 bg-card/95 p-6 shadow-sm">
				<div class="flex items-center justify-between gap-3">
					<div>
						<h1 class="text-2xl font-semibold tracking-tight">Chi phí phát sinh</h1>
						<p class="mt-1 text-sm text-muted-foreground">Quản lý danh mục phụ thu/giảm trừ phát sinh áp dụng trên đơn bán hàng.</p>
					</div>
					<Button as-child>
						<Link :href="tenantRoutes.surcharges.create()">
							<Plus class="size-4" />
							Thêm chi phí phát sinh
						</Link>
					</Button>
				</div>

				<div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Tổng mục chi phí</p>
						<p class="mt-1 text-2xl font-semibold">{{ surcharges.length }}</p>
					</div>
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Đang hoạt động</p>
						<p class="mt-1 text-2xl font-semibold">{{ activeCount }}</p>
					</div>
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Tự động áp dụng</p>
						<p class="mt-1 text-2xl font-semibold">{{ autoApplyCount }}</p>
					</div>
				</div>
			</section>

			<section class="overflow-hidden rounded-2xl border border-border/70 bg-card">
				<div class="overflow-x-auto">
					<table class="w-full text-sm">
						<thead class="bg-muted/40 text-left">
							<tr>
								<th class="p-3 font-medium">Mã</th>
								<th class="p-3 font-medium">Tên chi phí</th>
								<th class="p-3 font-medium">Loại tính</th>
								<th class="p-3 text-right font-medium">Giá trị</th>
								<th class="p-3 font-medium">Thuộc tính</th>
								<th class="p-3 text-center font-medium">Trạng thái</th>
								<th class="p-3 text-center font-medium">Thao tác</th>
							</tr>
						</thead>
						<tbody>
							<tr v-if="surcharges.length === 0">
								<td colspan="7" class="p-8 text-center text-muted-foreground">Chưa có chi phí phát sinh nào.</td>
							</tr>
							<tr v-for="row in surcharges" :key="row.id" class="border-t hover:bg-muted/20">
								<td class="p-3 font-mono text-xs">{{ row.code }}</td>
								<td class="p-3">
									<p class="font-semibold">{{ row.name }}</p>
									<p class="text-xs text-muted-foreground">{{ row.category || 'Không phân loại' }}</p>
								</td>
								<td class="p-3">{{ row.type === 'PERCENT' ? 'Phần trăm' : 'Số tiền cố định' }}</td>
								<td class="p-3 text-right font-medium">{{ formatValue(row) }}</td>
								<td class="p-3">
									<div class="flex flex-wrap gap-1">
										<Badge v-if="row.is_auto_apply" variant="outline">Tự động áp dụng</Badge>
										<Badge v-if="row.is_refundable" variant="outline">Hoàn trả được</Badge>
									</div>
								</td>
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
												<DropdownMenuItem as-child><Link :href="tenantRoutes.surcharges.show(row.id)" class="inline-flex w-full items-center gap-2"><Eye class="size-4" />Xem</Link></DropdownMenuItem>
												<DropdownMenuItem as-child><Link :href="tenantRoutes.surcharges.edit(row.id)" class="inline-flex w-full items-center gap-2"><Pencil class="size-4" />Sửa</Link></DropdownMenuItem>
												<DropdownMenuItem class="text-destructive focus:text-destructive" @click="deleteSurcharge(row.id)"><Trash2 class="size-4" />Xóa</DropdownMenuItem>
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
