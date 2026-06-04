<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from '@/components/ui/dialog';
import {
	FilterBar,
	FilterDateRangePicker,
	FilterDebtToggle,
	FilterOrderStatusSelect,
	FilterResetButton,
	FilterSearchInput,
	FilterTagList,
} from '@/components/ui/filter';

type OrderRow = {
	id: number;
	code?: string;
	status?: string;
	order_date?: string | null;
	created_at?: string | null;
	customer?: { id?: number; name?: string } | null;
	final_amount?: number | string | null;
	debt_amount?: number | string | null;
};

const props = defineProps<{
	open: boolean;
	orders: OrderRow[];
	selectedOrderId?: number | null;
}>();

const emit = defineEmits<{
	(event: 'update:open', value: boolean): void;
	(event: 'select', orderId: number): void;
}>();

const filters = ref({
	keyword: '',
	status: '',
	debtOnly: '',
	fromAt: '',
	toAt: '',
});

const toNumber = (value: number | string | null | undefined) => {
	if (value === null || value === undefined) return 0;
	const parsed = typeof value === 'string' ? parseFloat(value) : Number(value);
	return Number.isFinite(parsed) ? parsed : 0;
};

const formatCurrency = (value: number | string | null | undefined) => {
	return new Intl.NumberFormat('vi-VN').format(toNumber(value));
};

const normalizeText = (value: string | null | undefined): string => {
	return String(value ?? '')
		.toLowerCase()
		.normalize('NFD')
		.replace(/[\u0300-\u036f]/g, '')
		.replace(/đ/g, 'd')
		.replace(/[^a-z0-9\s]/g, ' ')
		.replace(/\s+/g, ' ')
		.trim();
};

const getOrderTimestamp = (order: OrderRow) => {
	const raw = order.order_date || order.created_at;
	if (!raw) return null;
	const parsed = new Date(raw);
	return Number.isNaN(parsed.getTime()) ? null : parsed.getTime();
};

const withinDateRange = (order: OrderRow) => {
	const fromAt = filters.value.fromAt;
	const toAt = filters.value.toAt;
	if (fromAt === '' && toAt === '') return true;

	const timestamp = getOrderTimestamp(order);
	if (timestamp === null) return false;

	if (fromAt) {
		const from = new Date(`${fromAt}T00:00:00`).getTime();
		if (timestamp < from) return false;
	}

	if (toAt) {
		const to = new Date(`${toAt}T23:59:59`).getTime();
		if (timestamp > to) return false;
	}

	return true;
};

const filteredOrders = computed(() => {
	const keyword = normalizeText(filters.value.keyword);

	return props.orders
		.filter((order) => {
			if (keyword === '') return true;

			const searchable = normalizeText([
				order.code,
				order.customer?.name,
				order.status,
			].join(' '));

			return searchable.includes(keyword);
		})
		.filter((order) => {
			if (filters.value.status === '') return true;
			return String(order.status ?? '').toUpperCase() === filters.value.status;
		})
		.filter((order) => {
			if (filters.value.debtOnly !== 'true') return true;
			return toNumber(order.debt_amount) > 0;
		})
		.filter(withinDateRange)
		.slice(0, 200);
});

const isOrderReturnable = (order: OrderRow) => {
	return String(order.status ?? '').toUpperCase() === 'COMPLETED';
};

const activeTags = computed(() => {
	const tags: Array<{ key: string; label: string; value: string }> = [];

	if (filters.value.keyword.trim() !== '') {
		tags.push({ key: 'keyword', label: 'Từ khóa', value: filters.value.keyword.trim() });
	}

	if (filters.value.status !== '') {
		const labelMap: Record<string, string> = {
			DRAFT: 'Nháp',
			PREORDER: 'Đặt trước',
			PROCESSING: 'Đang xử lý',
			CONFIRMED: 'Đã xác nhận',
			COMPLETED: 'Hoàn thành',
			CANCELLED: 'Đã hủy',
		};

		tags.push({ key: 'status', label: 'Trạng thái', value: labelMap[filters.value.status] ?? filters.value.status });
	}

	if (filters.value.debtOnly === 'true') {
		tags.push({ key: 'debtOnly', label: 'Công nợ', value: 'Có nợ' });
	}

	if (filters.value.fromAt !== '') {
		tags.push({ key: 'fromAt', label: 'Từ ngày', value: filters.value.fromAt });
	}

	if (filters.value.toAt !== '') {
		tags.push({ key: 'toAt', label: 'Đến ngày', value: filters.value.toAt });
	}

	return tags;
});

const resetFilters = () => {
	filters.value = {
		keyword: '',
		status: '',
		debtOnly: '',
		fromAt: '',
		toAt: '',
	};
};

const removeTag = (key: string) => {
	if (key in filters.value) {
		(filters.value as Record<string, string>)[key] = '';
	}
};

const selectedOrder = computed(() => filteredOrders.value.find((order) => order.id === props.selectedOrderId) ?? null);

watch(() => props.open, (isOpen) => {
	if (!isOpen) {
		resetFilters();
	}
});
</script>

<template>
	<Dialog :open="props.open" @update:open="(value) => emit('update:open', value)">
		<DialogContent class="sm:max-w-4xl">
			<DialogHeader>
				<DialogTitle>Chọn đơn hàng nguồn để trả hàng</DialogTitle>
				<DialogDescription>
					Chỉ đơn hàng ở trạng thái COMPLETED mới được phép chọn để tạo phiếu trả.
				</DialogDescription>
			</DialogHeader>

			<FilterBar>
				<FilterSearchInput
					v-model="filters.keyword"
					placeholder="Tìm theo mã đơn hoặc khách hàng"
					@search="() => {}"
				/>
				<FilterOrderStatusSelect v-model="filters.status" />
				<FilterDebtToggle v-model="filters.debtOnly" />
				<FilterDateRangePicker
					:from="filters.fromAt"
					:to="filters.toAt"
					@update:from="(v) => (filters.fromAt = v)"
					@update:to="(v) => (filters.toAt = v)"
				/>
				<FilterResetButton :count="activeTags.length" @reset="resetFilters" />

				<template #tags>
					<FilterTagList :tags="activeTags" @remove="removeTag" />
				</template>
			</FilterBar>

			<div class="max-h-[55vh] overflow-auto rounded-xl border border-border/70">
				<table class="w-full text-sm">
					<thead class="bg-muted/40 text-muted-foreground">
						<tr>
							<th class="px-3 py-2 text-left font-medium">Mã đơn</th>
							<th class="px-3 py-2 text-left font-medium">Khách hàng</th>
							<th class="px-3 py-2 text-left font-medium">Trạng thái</th>
							<th class="px-3 py-2 text-right font-medium">Tổng tiền</th>
							<th class="px-3 py-2 text-right font-medium">Công nợ</th>
							<th class="px-3 py-2 text-right font-medium">Chọn</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="order in filteredOrders" :key="order.id" class="border-t border-border/70">
							<td class="px-3 py-2 font-medium">{{ order.code || `#${order.id}` }}</td>
							<td class="px-3 py-2">{{ order.customer?.name || 'Khách lẻ' }}</td>
							<td class="px-3 py-2">
								<div class="flex items-center gap-2">
									<span>{{ order.status || '-' }}</span>
									<span
										v-if="!isOrderReturnable(order)"
										class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-800"
									>
										Không đủ điều kiện trả
									</span>
								</div>
							</td>
							<td class="px-3 py-2 text-right">{{ formatCurrency(order.final_amount) }}</td>
							<td class="px-3 py-2 text-right">{{ formatCurrency(order.debt_amount) }}</td>
							<td class="px-3 py-2 text-right">
								<Button type="button" size="sm" :disabled="!isOrderReturnable(order)" @click="emit('select', order.id)">
									Chọn
								</Button>
							</td>
						</tr>
						<tr v-if="filteredOrders.length === 0">
							<td colspan="6" class="px-3 py-6 text-center text-muted-foreground">
								Không có đơn hàng phù hợp bộ lọc.
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<DialogFooter class="flex items-center justify-between gap-2">
				<div class="text-xs text-muted-foreground">
					{{ selectedOrder ? `Đã chọn: ${selectedOrder.code || `#${selectedOrder.id}`}` : 'Chưa chọn đơn hàng nguồn' }}
				</div>
				<Button type="button" variant="outline" @click="emit('update:open', false)">
					Đóng
				</Button>
			</DialogFooter>
		</DialogContent>
	</Dialog>
</template>
