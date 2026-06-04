<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { tenantRoutes } from '@/lib/tenantRoutes';

type DebtOrder = {
	id: number;
	code: string;
	order_date?: string | null;
	created_at?: string | null;
	final_amount: number;
	paid_amount: number;
	debt_amount: number;
};

type PaymentMethod = {
	id: number;
	code: string;
	name: string;
	type?: string | null;
	is_default: boolean;
};

type Collector = {
	id: number;
	name: string;
};

const props = defineProps<{
	open: boolean;
	customer: { id: number; name: string; phone?: string | null };
	orders: DebtOrder[];
	paymentMethods: PaymentMethod[];
	collectors: Collector[];
	authUserId: number;
}>();

const emit = defineEmits<{
	'update:open': [value: boolean];
}>();

const form = reactive({
	amount: 0,
	method: '',
	collector_id: 0,
	paid_at: '',
	note: '',
});

const allocations = reactive<Record<number, number>>({});
const errors = reactive<Record<string, string>>({});
const processing = reactive({ value: false });

const toNumber = (value: number | string | null | undefined) => Number(value ?? 0);
const formatCurrency = (value: number | string | null | undefined) => new Intl.NumberFormat('vi-VN').format(toNumber(value));
const formatDateTime = (value: string | null | undefined) => {
	if (!value) return '-';
	const date = new Date(value);
	if (Number.isNaN(date.getTime())) return String(value);
	return date.toLocaleString('vi-VN');
};

const defaultMethodCode = computed(() => {
	const defaultMethod = props.paymentMethods.find((row) => row.is_default) ?? props.paymentMethods[0];
	return defaultMethod?.code ?? 'CASH';
});

const sortedOrders = computed(() => {
	return [...props.orders].sort((left, right) => {
		const leftTime = new Date(left.order_date || left.created_at || 0).getTime();
		const rightTime = new Date(right.order_date || right.created_at || 0).getTime();
		if (leftTime !== rightTime) return leftTime - rightTime;
		return left.id - right.id;
	});
});

const totalDebt = computed(() => sortedOrders.value.reduce((sum, row) => sum + Math.max(0, Number(row.debt_amount || 0)), 0));
const totalPayment = computed(() => Object.values(allocations).reduce((sum, amount) => sum + Number(amount || 0), 0));
const remainingAfterPayment = computed(() => Math.max(0, totalDebt.value - totalPayment.value));

const resetErrors = () => {
	Object.keys(errors).forEach((key) => {
		delete errors[key];
	});
};

const clearAllocations = () => {
	Object.keys(allocations).forEach((key) => {
		delete allocations[Number(key)];
	});
	sortedOrders.value.forEach((row) => {
		allocations[row.id] = 0;
	});
};

const distributePaymentFIFO = () => {
	clearAllocations();
	let remaining = Math.max(0, Number(form.amount || 0));

	sortedOrders.value.forEach((row) => {
		if (remaining <= 0) {
			allocations[row.id] = 0;
			return;
		}

		const debt = Math.max(0, Number(row.debt_amount || 0));
		const allocated = Math.min(debt, remaining);
		allocations[row.id] = allocated;
		remaining = Math.max(0, remaining - allocated);
	});
};

const fillAllDebt = () => {
	form.amount = totalDebt.value;
	distributePaymentFIFO();
};

const resetForm = () => {
	form.amount = 0;
	form.method = defaultMethodCode.value;
	form.collector_id = props.authUserId || 0;
	form.paid_at = '';
	form.note = '';
	clearAllocations();
	resetErrors();
};

watch(
	() => props.open,
	(open) => {
		if (!open) return;
		resetForm();
	},
);

watch(
	() => form.amount,
	(value) => {
		const normalized = Math.max(0, Math.min(Number(value || 0), totalDebt.value));
		if (normalized !== value) {
			form.amount = normalized;
		}
		distributePaymentFIFO();
	},
);

const canSubmit = computed(() => {
	if (processing.value) return false;
	if (sortedOrders.value.length === 0) return false;
	if (Number(form.amount || 0) <= 0) return false;
	if (totalPayment.value <= 0) return false;
	if (!form.method) return false;

	return true;
});

const submit = async () => {
	if (!canSubmit.value) return;

	processing.value = true;
	resetErrors();

	try {
		const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
		const response = await fetch(tenantRoutes.payments.batchDebt(), {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				Accept: 'application/json',
				'X-CSRF-TOKEN': csrf,
				'X-Requested-With': 'XMLHttpRequest',
			},
			credentials: 'same-origin',
			body: JSON.stringify({
				customer_id: props.customer.id,
				amount: Number(form.amount || 0),
				method: form.method,
				collector_id: form.collector_id || null,
				paid_at: form.paid_at || null,
				note: form.note || null,
				order_ids: sortedOrders.value.map((row) => row.id),
			}),
		});

		if (!response.ok) {
			const payload = await response.json().catch(() => null);
			if (response.status === 422 && payload?.errors) {
				Object.entries(payload.errors as Record<string, string[] | string>).forEach(([key, value]) => {
					errors[key] = Array.isArray(value) ? String(value[0] ?? '') : String(value ?? '');
				});
				return;
			}

			errors.general = payload?.message || 'Không thể ghi nhận thanh toán công nợ. Vui lòng thử lại.';
			return;
		}

		emit('update:open', false);
		router.reload({
			only: ['orders', 'debtEntries', 'currentDebt', 'salesSummary'],
		});
	} catch {
		errors.general = 'Không thể kết nối máy chủ. Vui lòng thử lại.';
	} finally {
		processing.value = false;
	}
};
</script>

<template>
	<Dialog :open="open" @update:open="(value) => emit('update:open', value)">
		<DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-6xl">
			<DialogHeader>
				<DialogTitle>Thanh toán công nợ</DialogTitle>
				<DialogDescription>
					{{ props.customer.name }} • Nợ hiện tại: {{ formatCurrency(totalDebt) }}
				</DialogDescription>
			</DialogHeader>

			<div class="space-y-4">
				<div class="grid gap-3 md:grid-cols-2">
					<div class="space-y-2">
						<Label>Thời gian</Label>
						<Input v-model="form.paid_at" type="datetime-local" />
						<p v-if="errors.paid_at" class="text-xs text-red-600">{{ errors.paid_at }}</p>
					</div>
					<div class="space-y-2">
						<Label>Người thu</Label>
						<select v-model.number="form.collector_id" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm outline-none">
							<option v-for="collector in collectors" :key="collector.id" :value="collector.id">
								{{ collector.name }}
							</option>
						</select>
						<p v-if="errors.collector_id" class="text-xs text-red-600">{{ errors.collector_id }}</p>
					</div>
				</div>

				<div class="grid gap-3 md:grid-cols-2">
					<div class="space-y-2">
						<Label>Phương thức thanh toán</Label>
						<select v-model="form.method" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm outline-none">
							<option v-for="method in paymentMethods" :key="method.id" :value="method.code">
								{{ method.name }} ({{ method.code }})
							</option>
						</select>
						<p v-if="errors.method" class="text-xs text-red-600">{{ errors.method }}</p>
					</div>
					<div class="space-y-2">
						<Label>Số tiền thanh toán</Label>
						<div class="flex gap-2">
							<Input v-model.number="form.amount" type="number" min="0" :max="totalDebt" step="1000" class="text-right" />
							<Button type="button" variant="outline" @click="fillAllDebt">Thanh toán hết</Button>
						</div>
						<p class="text-xs text-muted-foreground">
							Phân bổ theo đơn hàng từ đơn cũ nhất đến mới nhất.
						</p>
						<p v-if="errors.amount" class="text-xs text-red-600">{{ errors.amount }}</p>
					</div>
				</div>

				<div class="space-y-2">
					<Label>Ghi chú</Label>
					<textarea v-model="form.note" rows="2" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm outline-none resize-none"></textarea>
					<p v-if="errors.note" class="text-xs text-red-600">{{ errors.note }}</p>
				</div>

				<div class="overflow-hidden rounded-xl border border-border/70">
					<table class="w-full text-sm">
						<thead class="bg-muted/40 text-left">
							<tr>
								<th class="p-3 font-medium">Mã hóa đơn</th>
								<th class="p-3 font-medium">Thời gian</th>
								<th class="p-3 text-right font-medium">Giá trị hóa đơn</th>
								<th class="p-3 text-right font-medium">Đã thu trước</th>
								<th class="p-3 text-right font-medium">Còn cần thu</th>
								<th class="p-3 text-right font-medium">Tiền thu</th>
							</tr>
						</thead>
						<tbody>
							<tr v-if="sortedOrders.length === 0">
								<td colspan="6" class="p-6 text-center text-muted-foreground">Không có đơn hàng công nợ để phân bổ.</td>
							</tr>
							<tr v-for="row in sortedOrders" :key="row.id" class="border-t">
								<td class="p-3">{{ row.code }}</td>
								<td class="p-3">{{ formatDateTime(row.order_date || row.created_at) }}</td>
								<td class="p-3 text-right">{{ formatCurrency(row.final_amount) }}</td>
								<td class="p-3 text-right">{{ formatCurrency(row.paid_amount) }}</td>
								<td class="p-3 text-right font-semibold text-red-600">{{ formatCurrency(row.debt_amount) }}</td>
								<td class="p-3 text-right font-semibold text-emerald-600">{{ formatCurrency(allocations[row.id]) }}</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="grid gap-3 rounded-lg border border-border/70 bg-muted/20 p-3 text-sm md:grid-cols-3">
					<div>
						<p class="text-xs text-muted-foreground">Tổng nợ</p>
						<p class="font-semibold text-red-600">{{ formatCurrency(totalDebt) }}</p>
					</div>
					<div>
						<p class="text-xs text-muted-foreground">Tổng thanh toán</p>
						<p class="font-semibold text-emerald-600">{{ formatCurrency(totalPayment) }}</p>
					</div>
					<div>
						<p class="text-xs text-muted-foreground">Nợ còn lại</p>
						<p class="font-semibold">{{ formatCurrency(remainingAfterPayment) }}</p>
					</div>
				</div>

				<p v-if="errors.order_ids" class="text-xs text-red-600">{{ errors.order_ids }}</p>
				<p v-if="errors.general" class="text-xs text-red-600">{{ errors.general }}</p>
			</div>

			<DialogFooter class="gap-2">
				<Button type="button" variant="outline" @click="emit('update:open', false)">Bỏ qua</Button>
				<Button type="button" :disabled="!canSubmit || processing.value" @click="submit">
					{{ processing.value ? 'Đang xử lý...' : 'Tạo phiếu thu' }}
				</Button>
			</DialogFooter>
		</DialogContent>
	</Dialog>
</template>
