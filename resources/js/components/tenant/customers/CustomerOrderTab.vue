<script setup lang="ts">
import { computed } from 'vue';

type OrderRow = {
	id: number;
	code: string;
	status: string;
	type?: string | null;
	order_date?: string | null;
	created_at?: string | null;
	created_by?: string | null;
	final_amount: number;
	paid_amount: number;
	debt_amount: number;
	return_orders?: Array<{
		id: number;
		code?: string | null;
		status?: string | null;
		total_amount?: number;
		return_date?: string | null;
		created_at?: string | null;
	}>;
};

type ReturnOrderRow = {
	id: number;
	order_id?: number | null;
	code: string;
	status: string;
	return_date?: string | null;
	created_at?: string | null;
	total_amount: number;
	created_by?: string | null;
};

type DebtEntryRow = {
	id: number;
	order_id?: number | null;
	type: string;
	status: string;
	paid_at?: string | null;
	created_at?: string | null;
};

const props = defineProps<{
	orders: OrderRow[];
	returnOrders: ReturnOrderRow[];
	debtEntries: DebtEntryRow[];
	salesSummary: {
		total_sold: number;
		total_return: number;
		total_sold_minus_return: number;
	};
}>();

const emit = defineEmits<{
	'open-order': [orderId: number];
}>();


const formatCurrency = (value: number | string | null | undefined) => Number(value || 0).toLocaleString('vi-VN');
const formatDateTime = (value: string | null | undefined) => {
	if (!value) return '-';
	const date = new Date(value);
	if (Number.isNaN(date.getTime())) return String(value);

	return date.toLocaleString('vi-VN');
};

const orderStatusText = (status: string | null | undefined): string => {
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

const returnStatusText = (status: string | null | undefined): string => {
	const map: Record<string, string> = {
		PENDING: 'Chờ xử lý',
		APPROVED: 'Đã duyệt',
		REJECTED: 'Từ chối',
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
		PENDING: 'bg-amber-100 text-amber-700',
		APPROVED: 'bg-indigo-100 text-indigo-700',
		REJECTED: 'bg-rose-100 text-rose-700',
		CANCELLED: 'bg-red-100 text-red-700',
	};

	return map[key] ?? 'bg-slate-100 text-slate-700';
};

const receivableTypeText = (type: string | null | undefined): string => {
	const map: Record<string, string> = {
		INVOICE: 'Ghi nhận công nợ (Hóa đơn)',
		PAYMENT: 'Thu tiền công nợ',
		CREDIT_NOTE: 'Giảm công nợ (Credit note)',
		DEBIT_NOTE: 'Tăng công nợ (Debit note)',
		ADJUST: 'Điều chỉnh công nợ',
		WRITE_OFF: 'Xóa nợ',
	};

	const key = String(type ?? '').toUpperCase();
	return map[key] ?? (type || '-');
};

const receivableStatusText = (status: string | null | undefined): string => {
	const map: Record<string, string> = {
		OPEN: 'Mở',
		PARTIAL: 'Thanh toán một phần',
		PAID: 'Đã thanh toán',
		OVERDUE: 'Quá hạn',
		WRITTEN_OFF: 'Đã xóa nợ',
	};

	const key = String(status ?? '').toUpperCase();
	return map[key] ?? (status || '-');
};

const receivableByOrderId = computed(() => {
	const map = new Map<number, DebtEntryRow>();

	const orderedEntries = [...props.debtEntries].sort((left, right) => {
		const leftTime = new Date(left.paid_at || left.created_at || 0).getTime();
		const rightTime = new Date(right.paid_at || right.created_at || 0).getTime();
		return rightTime - leftTime;
	});

	orderedEntries.forEach((entry) => {
		const orderId = Number(entry.order_id || 0);
		if (orderId <= 0 || map.has(orderId)) return;
		map.set(orderId, entry);
	});

	return map;
});

const historyRows = computed(() => {
	const sales = props.orders.map((row) => ({
		id: row.id,
		order_id: row.id,
		code: row.code,
		status: row.status,
		time: row.order_date || row.created_at || null,
		created_by: row.created_by || null,
		total_amount: Number(row.final_amount || 0),
		receivable_entry: receivableByOrderId.value.get(row.id) ?? null,
		entry_type: 'SALE' as const,
	}));

	const returns = props.returnOrders.map((row) => ({
		id: row.id,
		order_id: row.order_id ?? null,
		code: row.code,
		status: row.status,
		time: row.return_date || row.created_at || null,
		created_by: row.created_by || null,
		total_amount: Number(row.total_amount || 0),
		receivable_entry: row.order_id ? receivableByOrderId.value.get(row.order_id) ?? null : null,
		entry_type: 'RETURN' as const,
	}));

	return [...sales, ...returns].sort((left, right) => {
		const leftTime = left.time ? new Date(left.time).getTime() : 0;
		const rightTime = right.time ? new Date(right.time).getTime() : 0;
		return rightTime - leftTime;
	});
});

const openOrderDetail = (orderId: number | null | undefined) => {
	if (!orderId) return;
	emit('open-order', orderId);
};
</script>

<template>
	<div class="space-y-4">
		<section class="grid grid-cols-1 gap-3 md:grid-cols-3">
			<div class="rounded-xl border border-border/70 bg-card p-4">
				<p class="text-xs uppercase tracking-wide text-muted-foreground">Tổng bán</p>
				<p class="mt-1 text-2xl font-semibold">{{ formatCurrency(salesSummary.total_sold) }}</p>
			</div>
			<div class="rounded-xl border border-border/70 bg-card p-4">
				<p class="text-xs uppercase tracking-wide text-muted-foreground">Tổng trả hàng</p>
				<p class="mt-1 text-2xl font-semibold">{{ formatCurrency(salesSummary.total_return) }}</p>
			</div>
			<div class="rounded-xl border border-border/70 bg-card p-4">
				<p class="text-xs uppercase tracking-wide text-muted-foreground">Tổng bán trừ trả hàng</p>
				<p class="mt-1 text-2xl font-semibold">{{ formatCurrency(salesSummary.total_sold_minus_return) }}</p>
			</div>
		</section>

		<section class="overflow-hidden rounded-2xl border border-border/70 bg-card">
			<table class="w-full text-sm">
				<thead class="bg-muted/40 text-left">
					<tr>
						<th class="p-3 font-medium">Mã phiếu</th>
						<th class="p-3 font-medium">Thời gian</th>
						<th class="p-3 font-medium">Loại</th>
						<th class="p-3 font-medium">Nghiệp vụ công nợ</th>
						<th class="p-3 font-medium">Người tạo</th>
						<th class="p-3 text-right font-medium">Tổng tiền</th>
						<th class="p-3 text-center font-medium">Trạng thái</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="historyRows.length === 0">
						<td colspan="7" class="p-6 text-center text-muted-foreground">Chưa có lịch sử bán/trả hàng.</td>
					</tr>
					<tr v-for="row in historyRows" :key="`${row.entry_type}-${row.id}`" class="border-t">
						<td class="p-3 font-medium">
							<button
								type="button"
								class="text-primary hover:underline"
								:disabled="!row.order_id"
								@click="openOrderDetail(row.order_id)"
							>
								{{ row.code }}
							</button>
						</td>
						<td class="p-3">{{ formatDateTime(row.time) }}</td>
						<td class="p-3">{{ row.entry_type === 'RETURN' ? 'Trả hàng' : 'Bán hàng' }}</td>
						<td class="p-3">
							<div v-if="row.receivable_entry" class="space-y-1">
								<p class="text-xs font-medium">{{ receivableTypeText(row.receivable_entry.type) }}</p>
								<p class="text-xs text-muted-foreground">{{ receivableStatusText(row.receivable_entry.status) }}</p>
							</div>
							<span v-else class="text-muted-foreground">-</span>
						</td>
						<td class="p-3">{{ row.created_by || '-' }}</td>
						<td class="p-3 text-right" :class="row.entry_type === 'RETURN' ? 'text-red-600 font-semibold' : ''">{{ formatCurrency(row.total_amount) }}</td>
						<td class="p-3 text-center">
							<span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadgeClass(row.status)">
								{{ row.entry_type === 'RETURN' ? returnStatusText(row.status) : orderStatusText(row.status) }}
							</span>
						</td>
					</tr>
				</tbody>
			</table>
		</section>
	</div>
</template>
