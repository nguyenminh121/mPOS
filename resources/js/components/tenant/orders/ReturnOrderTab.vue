<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type ReturnOrderSummary = {
	id: number;
	code?: string;
	status?: string;
	total_amount?: number | string;
	return_date?: string | null;
	created_at?: string | null;
};

type OrderDetail = {
	id: number;
	code: string;
	type?: string | null;
	return_orders?: ReturnOrderSummary[];
	returnOrders?: ReturnOrderSummary[];
};

const props = defineProps<{
	order: OrderDetail;
}>();

const toNumber = (value: number | string | null | undefined): number => {
	if (value === null || value === undefined) return 0;
	const parsed = typeof value === 'string' ? parseFloat(value) : Number(value);
	return Number.isFinite(parsed) ? parsed : 0;
};

const formatCurrency = (value: number | string | null | undefined): string => {
	return new Intl.NumberFormat('vi-VN').format(toNumber(value));
};

const formatDate = (value: string | null | undefined): string => {
	if (!value) return '-';
	const parsed = new Date(value);
	if (Number.isNaN(parsed.getTime())) return '-';
	return new Intl.DateTimeFormat('vi-VN', {
		year: 'numeric',
		month: '2-digit',
		day: '2-digit',
		hour: '2-digit',
		minute: '2-digit',
	}).format(parsed);
};

const returnStatusText = (value: string | null | undefined): string => {
	const map: Record<string, string> = {
		DRAFT: 'Nháp',
		PENDING: 'Chờ xử lý',
		APPROVED: 'Đã duyệt',
		COMPLETED: 'Hoàn thành',
		CANCELLED: 'Đã hủy',
	};

	const status = String(value ?? '').toUpperCase();
	return map[status] ?? (value || '-');
};

const returnRows = computed<ReturnOrderSummary[]>(() => {
	const fromSnake = props.order.return_orders ?? [];
	if (fromSnake.length > 0) return fromSnake;
	return props.order.returnOrders ?? [];
});

const totalReturnedAmount = computed(() => {
	return returnRows.value.reduce((sum, row) => sum + toNumber(row.total_amount), 0);
});
</script>

<template>
	<div v-if="returnRows.length > 0" class="space-y-3">
		<div class="rounded-xl border border-border/70 bg-background/50 p-3">
			<div class="flex items-center justify-between text-sm">
				<span class="text-muted-foreground">Số phiếu trả</span>
				<span class="font-medium">{{ returnRows.length }}</span>
			</div>
			<div class="mt-2 flex items-center justify-between text-sm">
				<span class="text-muted-foreground">Tổng giá trị trả hàng</span>
				<span class="font-semibold">{{ formatCurrency(totalReturnedAmount) }}</span>
			</div>
		</div>

		<div class="rounded-xl border border-border/70 bg-background/50 p-3 text-sm text-muted-foreground">
			Danh sách phiếu trả phát sinh từ đơn này.
		</div>

		<div class="overflow-hidden rounded-xl border border-border/70">
			<table class="w-full text-sm">
				<thead class="bg-muted/40 text-muted-foreground">
					<tr>
						<th class="px-3 py-2 text-left font-medium">Mã trả hàng</th>
						<th class="px-3 py-2 text-left font-medium">Ngày</th>
						<th class="px-3 py-2 text-left font-medium">Trạng thái</th>
						<th class="px-3 py-2 text-right font-medium">Giá trị</th>
						<th class="px-3 py-2 text-right font-medium">Liên kết</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="row in returnRows" :key="row.id" class="border-t border-border/70">
						<td class="px-3 py-2 font-medium">{{ row.code || `#${row.id}` }}</td>
						<td class="px-3 py-2">{{ formatDate(row.return_date || row.created_at) }}</td>
						<td class="px-3 py-2">{{ returnStatusText(row.status) }}</td>
						<td class="px-3 py-2 text-right">{{ formatCurrency(row.total_amount) }}</td>
						<td class="px-3 py-2 text-right">
							<Link :href="tenantRoutes.returns.show(row.code || row.id)" class="text-primary hover:underline">Mở phiếu trả</Link>
						</td>
					</tr>
					<tr v-if="returnRows.length === 0">
						<td colspan="5" class="px-3 py-5 text-center text-muted-foreground">Chưa có dữ liệu trả hàng cho đơn này.</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</template>
