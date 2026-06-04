<script setup lang="ts">
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

type SaleItem = {
	product_variant_id?: number;
	unit_name?: string;
	conversion_rate?: number;
	quantity?: number;
	price?: number;
};

type SaleRow = {
	id?: number;
	code?: string;
	status?: string;
	order_date?: string;
	created_at?: string;
	final_amount?: number;
	surcharge?: number;
	customer_id?: number | null;
	items?: SaleItem[];
};

type PriceHistoryRecord = {
	price: number;
	quantity: number;
	unit_name: string;
	conversion_rate: number;
	order_code: string;
	order_status: string;
	order_date: string;
	total_amount: number;
	surcharge: number;
};

const props = defineProps<{
	open: boolean;
	title?: string;
	productVariantId: number | null;
	customerId: number | null;
	sales: SaleRow[];
}>();

const emit = defineEmits<{
	'update:open': [value: boolean];
	apply: [payload: {
		price: number;
		quantity: number;
		unit_name: string;
		conversion_rate: number;
	}];
}>();

const historyRows = computed<PriceHistoryRecord[]>(() => {
	if (!props.productVariantId) return [];

	const rows: PriceHistoryRecord[] = [];

	for (const sale of props.sales ?? []) {
		if (props.customerId !== null && Number(sale.customer_id ?? 0) !== Number(props.customerId)) {
			continue;
		}

		for (const item of sale.items ?? []) {
			if (Number(item.product_variant_id ?? 0) !== props.productVariantId) {
				continue;
			}

			rows.push({
				price: Number(item.price || 0),
				quantity: Number(item.quantity || 0),
				unit_name: String(item.unit_name || 'Cái'),
				conversion_rate: Number(item.conversion_rate || 1),
				order_code: String(sale.code || '-'),
				order_status: String(sale.status || '-'),
				order_date: String(sale.order_date || sale.created_at || ''),
				total_amount: Number(sale.final_amount || 0),
				surcharge: Number(sale.surcharge || 0),
			});
		}
	}

	return rows
		.sort((left, right) => new Date(right.order_date).getTime() - new Date(left.order_date).getTime())
		.slice(0, 20);
});

const formatDateTime = (value: string) => {
	if (!value) return '-';
	const date = new Date(value);
	if (Number.isNaN(date.getTime())) return '-';

	return date.toLocaleString('vi-VN', {
		day: '2-digit',
		month: '2-digit',
		year: 'numeric',
		hour: '2-digit',
		minute: '2-digit',
	});
};

const getStatusText = (status: string) => {
	const map: Record<string, string> = {
		DRAFT: 'Nháp',
		CONFIRMED: 'Xác nhận',
		PROCESSING: 'Đang xử lý',
		COMPLETED: 'Hoàn tất',
		CANCELLED: 'Đã hủy',
	};

	return map[status] ?? status;
};

const getStatusClass = (status: string) => {
	const map: Record<string, string> = {
		DRAFT: 'bg-muted text-muted-foreground',
		CONFIRMED: 'bg-blue-100 text-blue-700',
		PROCESSING: 'bg-amber-100 text-amber-700',
		COMPLETED: 'bg-green-100 text-green-700',
		CANCELLED: 'bg-red-100 text-red-700',
	};

	return map[status] ?? 'bg-muted text-muted-foreground';
};

const applyHistory = (row: PriceHistoryRecord) => {
	emit('apply', {
		price: row.price,
		quantity: row.quantity,
		unit_name: row.unit_name,
		conversion_rate: row.conversion_rate,
	});
};
</script>

<template>
	<Dialog :open="open" @update:open="(value) => emit('update:open', value)">
		<DialogContent class="max-h-[85vh] sm:max-w-3xl">
			<DialogHeader>
				<DialogTitle>{{ title || 'Giá bán gần đây' }}</DialogTitle>
				<DialogDescription>Chọn một dòng để áp dụng nhanh đơn giá vào sản phẩm hiện tại.</DialogDescription>
			</DialogHeader>

			<div v-if="historyRows.length > 0" class="max-h-[60vh] space-y-2 overflow-y-auto pr-1">
				<button
					v-for="(row, index) in historyRows"
					:key="`${row.order_code}-${index}`"
					type="button"
					class="w-full rounded-lg border border-border/70 p-3 text-left transition hover:bg-muted/60"
					@click="applyHistory(row)"
				>
					<div class="mb-2 flex items-start justify-between">
						<div>
							<p class="font-medium">{{ row.order_code }}</p>
							<p class="text-xs text-muted-foreground">{{ formatDateTime(row.order_date) }}</p>
						</div>
						<span :class="['rounded px-2 py-0.5 text-xs font-medium', getStatusClass(row.order_status)]">
							{{ getStatusText(row.order_status) }}
						</span>
					</div>

					<div class="grid grid-cols-2 gap-2 text-sm md:grid-cols-4">
						<div>
							<p class="text-muted-foreground">Đơn giá</p>
							<p class="font-medium">{{ Number(row.price || 0).toLocaleString('vi-VN') }}</p>
						</div>
						<div>
							<p class="text-muted-foreground">Số lượng</p>
							<p class="font-medium">{{ Number(row.quantity || 0).toLocaleString('vi-VN') }}</p>
						</div>
						<div>
							<p class="text-muted-foreground">Đơn vị</p>
							<p class="font-medium">{{ row.unit_name }}</p>
						</div>
						<div>
							<p class="text-muted-foreground">Tổng đơn</p>
							<p class="font-medium">{{ Number(row.total_amount || 0).toLocaleString('vi-VN') }}</p>
						</div>
					</div>
				</button>
			</div>

			<div v-else class="py-10 text-center text-sm text-muted-foreground">
				Không có lịch sử bán hàng phù hợp.
			</div>

			<DialogFooter>
				<Button type="button" variant="outline" @click="emit('update:open', false)">Đóng</Button>
			</DialogFooter>
		</DialogContent>
	</Dialog>
</template>
