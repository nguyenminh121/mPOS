<script setup lang="ts">
import { reactive } from 'vue';

const props = defineProps<{
	currentDebt: number;
	unpaidDebtOrders: Array<{
		id: number;
		code: string;
		order_date?: string | null;
		created_at?: string | null;
		final_amount: number;
		paid_amount: number;
		debt_amount: number;
	}>;
	debtEntries: Array<{
		id: number;
		order_id?: number | null;
		type: string;
		status: string;
		order_status?: string | null;
		amount: number;
		balance_after: number;
		order_code?: string | null;
		paid_at?: string | null;
		created_at?: string | null;
		note?: string | null;
	}>;
}>();

const emit = defineEmits<{
	'open-order': [orderId: number];
	'open-payment': [];
	'export-debt': [filters: { from_date?: string; to_date?: string; order_code?: string }];
}>();

const exportFilters = reactive({
	from_date: '',
	to_date: '',
	order_code: '',
});

const formatCurrency = (value: number | string | null | undefined) => Number(value || 0).toLocaleString('vi-VN');
const formatDateTime = (value: string | null | undefined) => {
	if (!value) return '-';
	const date = new Date(value);
	if (Number.isNaN(date.getTime())) return String(value);

	return date.toLocaleString('vi-VN');
};

const orderStatusText = (status: string | null | undefined) => {
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

const openOrderDetail = (orderId: number | null | undefined) => {
	if (!orderId) return;
	emit('open-order', orderId);
};

const openPaymentModal = () => {
	emit('open-payment');
};

const exportDebt = () => {
	emit('export-debt', {
		from_date: exportFilters.from_date || undefined,
		to_date: exportFilters.to_date || undefined,
		order_code: exportFilters.order_code.trim() || undefined,
	});
};
</script>

<template>
	<div class="space-y-4">
		<section class="rounded-xl border border-border/70 bg-card p-4">
			<div class="flex flex-wrap items-center justify-between gap-3">
				<div>
					<p class="text-xs uppercase tracking-wide text-muted-foreground">Công nợ hiện tại</p>
					<p class="mt-1 text-2xl font-semibold" :class="currentDebt > 0 ? 'text-red-600' : ''">{{ formatCurrency(currentDebt) }}</p>
				</div>
				<div class="flex flex-wrap items-center gap-2">
					<button
						type="button"
						class="inline-flex rounded-md border border-border/70 px-3 py-2 text-sm font-medium"
						@click="exportDebt"
					>
						Xuất file công nợ
					</button>
					<button
						type="button"
						class="inline-flex rounded-md bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-40"
						:disabled="unpaidDebtOrders.length === 0"
						@click="openPaymentModal"
					>
						Thanh toán
					</button>
				</div>
			</div>

			<div class="mt-4 grid gap-3 md:grid-cols-3">
				<div>
					<p class="mb-1 text-xs text-muted-foreground">Từ ngày</p>
					<input v-model="exportFilters.from_date" type="date" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm" />
				</div>
				<div>
					<p class="mb-1 text-xs text-muted-foreground">Đến ngày</p>
					<input v-model="exportFilters.to_date" type="date" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm" />
				</div>
				<div>
					<p class="mb-1 text-xs text-muted-foreground">Mã đơn</p>
					<input v-model="exportFilters.order_code" type="text" placeholder="Lọc theo mã đơn" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm" />
				</div>
			</div>
		</section>

		<section class="overflow-hidden rounded-2xl border border-border/70 bg-card">
			<div class="border-b border-border/70 bg-muted/30 px-4 py-3">
				<p class="text-sm font-semibold">Đơn hàng còn nợ</p>
			</div>
			<table class="w-full text-sm">
				<thead class="bg-muted/40 text-left">
					<tr>
						<th class="p-3 font-medium">Mã phiếu</th>
						<th class="p-3 font-medium">Thời gian</th>
						<th class="p-3 text-right font-medium">Giá trị</th>
						<th class="p-3 text-right font-medium">Đã thu trước</th>
						<th class="p-3 text-right font-medium">Dư nợ khách hàng</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="unpaidDebtOrders.length === 0">
						<td colspan="5" class="p-6 text-center text-muted-foreground">Không còn đơn hàng chưa thanh toán công nợ.</td>
					</tr>
					<tr v-for="row in unpaidDebtOrders" :key="row.id" class="border-t">
						<td class="p-3">
							<button type="button" class="text-primary hover:underline" @click="openOrderDetail(row.id)">{{ row.code }}</button>
						</td>
						<td class="p-3">{{ formatDateTime(row.order_date || row.created_at) }}</td>
						<td class="p-3 text-right">{{ formatCurrency(row.final_amount) }}</td>
						<td class="p-3 text-right">{{ formatCurrency(row.paid_amount) }}</td>
						<td class="p-3 text-right font-semibold text-red-600">{{ formatCurrency(row.debt_amount) }}</td>
					</tr>
				</tbody>
			</table>
		</section>

		<section class="overflow-hidden rounded-2xl border border-border/70 bg-card">
			<div class="border-b border-border/70 bg-muted/30 px-4 py-3">
				<p class="text-sm font-semibold">Lịch sử bút toán công nợ</p>
			</div>
			<table class="w-full text-sm">
				<thead class="bg-muted/40 text-left">
					<tr>
						<th class="p-3 font-medium">Thời gian</th>
						<th class="p-3 font-medium">Loại</th>
						<th class="p-3 font-medium">Mã đơn</th>
						<th class="p-3 text-center font-medium">Trạng thái</th>
						<th class="p-3 text-right font-medium">Biến động</th>
						<th class="p-3 text-right font-medium">Dư nợ sau cùng</th>
						<th class="p-3 font-medium">Ghi chú</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="debtEntries.length === 0">
						<td colspan="7" class="p-6 text-center text-muted-foreground">Chưa có bút toán công nợ.</td>
					</tr>
					<tr v-for="entry in debtEntries" :key="entry.id" class="border-t">
						<td class="p-3">{{ formatDateTime(entry.paid_at || entry.created_at) }}</td>
						<td class="p-3">{{ entry.type }}</td>
						<td class="p-3">
							<button
								type="button"
								class="text-primary hover:underline"
								:disabled="!entry.order_id"
								@click="openOrderDetail(entry.order_id)"
							>
								{{ entry.order_code || '-' }}
							</button>
						</td>
						<td class="p-3 text-center">
							<span v-if="entry.order_status" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadgeClass(entry.order_status)">
								{{ orderStatusText(entry.order_status) }}
							</span>
							<span v-else class="text-muted-foreground">-</span>
						</td>
						<td class="p-3 text-right" :class="entry.amount > 0 ? 'text-red-600 font-semibold' : 'text-emerald-600 font-semibold'">{{ formatCurrency(entry.amount) }}</td>
						<td class="p-3 text-right font-medium">{{ formatCurrency(entry.balance_after) }}</td>
						<td class="p-3">{{ entry.note || '-' }}</td>
					</tr>
				</tbody>
			</table>
		</section>
	</div>
</template>
