<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from '@/components/ui/dialog';
import { tenantRoutes } from '@/lib/tenantRoutes';

type OrderItem = {
	id: number;
	unit_name?: string | null;
	quantity?: number | string;
	price?: number | string;
	discount?: number | string;
	total?: number | string;
	product_variant?: {
		id?: number;
		name?: string | null;
		sku?: string | null;
		barcode?: string | null;
	} | null;
};

type ReturnOrderSummary = {
	id: number;
	code?: string | null;
	status?: string | null;
	total_amount?: number | string;
	return_date?: string | null;
	created_at?: string | null;
};

type OrderDetail = {
	id: number;
	code: string;
	status: string;
	type?: string | null;
	order_date?: string | null;
	created_at?: string | null;
	created_by?: string | null;
	total_amount?: number | string;
	discount?: number | string;
	surcharge?: number | string;
	final_amount?: number | string;
	items?: OrderItem[];
	return_orders?: ReturnOrderSummary[];
};

const props = defineProps<{
	open: boolean;
	order: OrderDetail | null;
}>();

const emit = defineEmits<{
	'update:open': [value: boolean];
}>();

const toNumber = (value: number | string | null | undefined): number => {
	const parsed = Number(value ?? 0);
	return Number.isFinite(parsed) ? parsed : 0;
};

const formatCurrency = (value: number | string | null | undefined): string => toNumber(value).toLocaleString('vi-VN');

const formatDateTime = (value: string | null | undefined): string => {
	if (!value) return '-';
	const date = new Date(value);
	if (Number.isNaN(date.getTime())) return '-';
	return date.toLocaleString('vi-VN');
};

const statusLabel = (status: string | null | undefined): string => {
	const map: Record<string, string> = {
		PREORDER: 'Đặt trước',
		DRAFT: 'Nháp',
		CONFIRMED: 'Đã xác nhận',
		PROCESSING: 'Đang xử lý',
		COMPLETED: 'Hoàn thành',
		CANCELLED: 'Đã hủy',
	};

	const key = String(status ?? '').toUpperCase();
	return map[key] ?? (status || '-');
};

const statusBadgeClass = (status: string | null | undefined): string => {
	const key = String(status ?? '').toUpperCase();
	const map: Record<string, string> = {
		PREORDER: 'bg-blue-100 text-blue-700',
		DRAFT: 'bg-slate-100 text-slate-700',
		CONFIRMED: 'bg-indigo-100 text-indigo-700',
		PROCESSING: 'bg-amber-100 text-amber-700',
		COMPLETED: 'bg-emerald-100 text-emerald-700',
		CANCELLED: 'bg-red-100 text-red-700',
	};

	return map[key] ?? 'bg-slate-100 text-slate-700';
};

const orderTypeLabel = (type: string | null | undefined): string => {
	const map: Record<string, string> = {
		RETAIL: 'Bán lẻ',
		WHOLESALE: 'Bán buôn',
		ONLINE: 'Bán online',
		RETURN: 'Trả hàng',
	};

	const key = String(type ?? '').toUpperCase();
	return map[key] ?? (type || '-');
};

const returnTotalAmount = computed(() => {
	if (!props.order?.return_orders?.length) return 0;
	return props.order.return_orders.reduce((sum, row) => sum + toNumber(row.total_amount), 0);
});

const finalAfterReturn = computed(() => {
	return Math.max(0, toNumber(props.order?.final_amount) - returnTotalAmount.value);
});
</script>

<template>
	<Dialog :open="open" @update:open="(value) => emit('update:open', value)">
		<DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-5xl">
			<DialogHeader>
				<DialogTitle>Chi tiết đơn hàng</DialogTitle>
				<DialogDescription>
					Xem nhanh thông tin bán hàng và đối trừ trả hàng của đơn.
				</DialogDescription>
			</DialogHeader>

			<div v-if="order" class="space-y-4">
				<div class="grid grid-cols-1 gap-3 md:grid-cols-5">
					<div class="rounded-lg border border-border/70 bg-background/50 p-3">
						<p class="text-xs uppercase text-muted-foreground">Mã đơn</p>
						<p class="mt-1 text-sm font-semibold">{{ order.code }}</p>
					</div>
					<div class="rounded-lg border border-border/70 bg-background/50 p-3">
						<p class="text-xs uppercase text-muted-foreground">Thời gian</p>
						<p class="mt-1 text-sm font-medium">{{ formatDateTime(order.order_date || order.created_at) }}</p>
					</div>
					<div class="rounded-lg border border-border/70 bg-background/50 p-3">
						<p class="text-xs uppercase text-muted-foreground">Loại</p>
						<p class="mt-1 text-sm font-medium">{{ orderTypeLabel(order.type) }}</p>
					</div>
					<div class="rounded-lg border border-border/70 bg-background/50 p-3">
						<p class="text-xs uppercase text-muted-foreground">Người tạo</p>
						<p class="mt-1 text-sm font-medium">{{ order.created_by || '-' }}</p>
					</div>
					<div class="rounded-lg border border-border/70 bg-background/50 p-3">
						<p class="text-xs uppercase text-muted-foreground">Trạng thái</p>
						<p class="mt-1">
							<span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadgeClass(order.status)">
								{{ statusLabel(order.status) }}
							</span>
						</p>
					</div>
				</div>

				<div class="overflow-hidden rounded-xl border border-border/70">
					<table class="w-full text-sm">
						<thead class="bg-muted/40 text-left text-muted-foreground">
							<tr>
								<th class="p-3 font-medium">Sản phẩm</th>
								<th class="p-3 text-right font-medium">Số lượng</th>
								<th class="p-3 text-right font-medium">Đơn giá</th>
								<th class="p-3 text-right font-medium">Giảm giá</th>
								<th class="p-3 text-right font-medium">Thành tiền</th>
							</tr>
						</thead>
						<tbody>
							<tr v-if="!order.items || order.items.length === 0">
								<td colspan="5" class="p-5 text-center text-muted-foreground">Đơn hàng chưa có sản phẩm.</td>
							</tr>
							<tr v-for="item in order.items || []" :key="item.id" class="border-t border-border/70">
								<td class="p-3">
									<p class="font-medium">{{ item.product_variant?.name || `#${item.id}` }}</p>
									<p class="text-xs text-muted-foreground">{{ item.unit_name || '-' }} · {{ item.product_variant?.sku || '-' }}</p>
								</td>
								<td class="p-3 text-right">{{ formatCurrency(item.quantity) }}</td>
								<td class="p-3 text-right">{{ formatCurrency(item.price) }}</td>
								<td class="p-3 text-right text-red-600">{{ formatCurrency(item.discount) }}</td>
								<td class="p-3 text-right font-semibold">{{ formatCurrency(item.total) }}</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="grid gap-3 md:grid-cols-2">
					<div class="rounded-lg border border-border/70 bg-background/50 p-3 text-sm">
						<div class="flex items-center justify-between">
							<span class="text-muted-foreground">Tổng đơn gốc</span>
							<span class="font-medium">{{ formatCurrency(order.final_amount) }}</span>
						</div>
						<div class="mt-2 flex items-center justify-between">
							<span class="text-muted-foreground">Tổng trả hàng</span>
							<span class="font-medium text-red-600">-{{ formatCurrency(returnTotalAmount) }}</span>
						</div>
						<div class="mt-2 flex items-center justify-between border-t border-border/70 pt-2 text-base">
							<span class="font-semibold">Giá trị sau đối trừ</span>
							<span class="font-semibold text-primary">{{ formatCurrency(finalAfterReturn) }}</span>
						</div>
					</div>

					<div class="rounded-lg border border-border/70 bg-background/50 p-3 text-sm">
						<p class="mb-2 font-medium">Đơn trả hàng liên quan</p>
						<div v-if="order.return_orders?.length" class="space-y-2">
							<div v-for="row in order.return_orders" :key="row.id" class="flex items-center justify-between gap-3">
								<span class="text-muted-foreground">{{ row.code || `#${row.id}` }}</span>
								<span class="font-medium">{{ formatCurrency(row.total_amount) }}</span>
							</div>
						</div>
						<p v-else class="text-muted-foreground">Không có đơn trả hàng.</p>
					</div>
				</div>
			</div>

			<DialogFooter>
				<Button type="button" variant="outline" @click="emit('update:open', false)">Đóng</Button>
				<Button v-if="order" as-child type="button">
					<Link :href="tenantRoutes.orders.show(order.code)">Mở phiếu</Link>
				</Button>
			</DialogFooter>
		</DialogContent>
	</Dialog>
</template>
