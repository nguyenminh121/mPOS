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

type VariantUnitOption = {
	name: string;
	conversion_rate: number;
};

type VariantStockOption = {
	warehouse_id: number;
	warehouse_name?: string | null;
	quantity: number;
};

type VariantOption = {
	id: number;
	product_id?: number | null;
	product_name?: string | null;
	name: string;
	sku?: string | null;
	barcode?: string | null;
	base_unit?: string | null;
	price: number;
	unit_options?: VariantUnitOption[];
	total_stock?: number;
	stock_by_warehouse?: VariantStockOption[];
};

const props = defineProps<{
	open: boolean;
	variant: VariantOption | null;
	variants: VariantOption[];
}>();

const emit = defineEmits<{
	'update:open': [value: boolean];
}>();

const currentStocks = computed(() => props.variant?.stock_by_warehouse ?? []);
const totalBaseStock = computed(() => Number(props.variant?.total_stock || 0));

const integerStockBreakdownLabel = computed(() => {
	const baseUnitName = props.variant?.base_unit?.trim() || 'Cái';
	let remainingBaseStock = Math.max(0, Math.floor(totalBaseStock.value));

	if (remainingBaseStock === 0) {
		return `0 ${baseUnitName}`;
	}

	const sortedUnits = [...unitOptions.value]
		.map((unit) => ({
			name: unit.name,
			conversion_rate: Math.max(1, Math.round(Number(unit.conversion_rate || 1))),
		}))
		.sort((left, right) => right.conversion_rate - left.conversion_rate);

	const parts: string[] = [];

	for (const unit of sortedUnits) {
		const unitRate = Math.max(1, unit.conversion_rate);
		const unitCount = Math.floor(remainingBaseStock / unitRate);

		if (unitCount <= 0) {
			continue;
		}

		parts.push(`${unitCount.toLocaleString('vi-VN')} ${unit.name}`);
		remainingBaseStock -= unitCount * unitRate;
	}

	if (parts.length === 0) {
		return `0 ${baseUnitName}`;
	}

	return parts.join(' + ');
});

const unitOptions = computed(() => {
	const base = props.variant?.base_unit?.trim() || 'Cái';
	const options = [{ name: base, conversion_rate: 1 }, ...(props.variant?.unit_options ?? [])];

	const unique = new Map<string, VariantUnitOption>();
	for (const option of options) {
		const key = option.name.trim().toLowerCase();
		if (!key) continue;
		if (!unique.has(key)) {
			unique.set(key, {
				name: option.name,
				conversion_rate: Number(option.conversion_rate || 1),
			});
		}
	}

	return Array.from(unique.values());
});

const siblingVariants = computed(() => {
	if (!props.variant?.product_id) return [];

	return props.variants
		.filter((item) => item.product_id === props.variant?.product_id)
		.sort((left, right) => String(left.name).localeCompare(String(right.name), 'vi'));
});
</script>

<template>
	<Dialog :open="open" @update:open="(value) => emit('update:open', value)">
		<DialogContent class="sm:max-w-2xl">
			<DialogHeader>
				<DialogTitle>Thông tin sản phẩm</DialogTitle>
				<DialogDescription>
					{{ variant?.product_name || '-' }}
				</DialogDescription>
			</DialogHeader>

			<div v-if="variant" class="space-y-4">
				<div class="rounded-lg border border-border/70 p-3">
					<div class="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
						<div>
							<p class="text-muted-foreground">Biến thể</p>
							<p class="font-medium">{{ variant.name }}</p>
						</div>
						<div>
							<p class="text-muted-foreground">SKU</p>
							<p class="font-medium">{{ variant.sku || '-' }}</p>
						</div>
						<div>
							<p class="text-muted-foreground">Barcode</p>
							<p class="font-medium">{{ variant.barcode || '-' }}</p>
						</div>
						<div>
							<p class="text-muted-foreground">Có thể bán</p>
							<p class="font-semibold">{{ Number(variant.total_stock || 0).toLocaleString('vi-VN') }} {{ variant.base_unit || 'Cái' }}</p>
						</div>
					</div>
				</div>

				<div class="space-y-2 rounded-lg border border-border/70 p-3">
					<p class="text-sm font-medium">Tồn kho theo kho</p>
					<div v-if="currentStocks.length > 0" class="space-y-2">
						<div v-for="stock in currentStocks" :key="stock.warehouse_id" class="flex items-center justify-between text-sm">
							<span class="text-muted-foreground">{{ stock.warehouse_name || `Kho #${stock.warehouse_id}` }}</span>
							<span class="font-medium">{{ Number(stock.quantity || 0).toLocaleString('vi-VN') }}</span>
						</div>
					</div>
					<p v-else class="text-sm text-muted-foreground">Chưa có tồn kho ở các kho.</p>
				</div>

				<div class="space-y-2 rounded-lg border border-border/70 p-3">
					<p class="text-sm font-medium">Quy đổi UOM</p>
					<div class="space-y-2">
						<div v-for="unit in unitOptions" :key="unit.name" class="flex items-center justify-between gap-3 text-sm">
							<span class="text-muted-foreground">{{ unit.name }}</span>
							<div class="text-right">
								<p class="font-medium">1 {{ unit.name }} = {{ Number(unit.conversion_rate || 1).toLocaleString('vi-VN') }} {{ variant.base_unit || 'Cái' }}</p>
							</div>
						</div>
						<p class="text-xs text-muted-foreground">Tồn quy đổi nguyên: {{ integerStockBreakdownLabel }}</p>
					</div>
				</div>

				<div class="space-y-2 rounded-lg border border-border/70 p-3">
					<p class="text-sm font-medium">Các biến thể của sản phẩm</p>
					<div class="space-y-2">
						<div v-for="item in siblingVariants" :key="item.id" class="flex items-center justify-between text-sm">
							<span class="text-muted-foreground">{{ item.name }}</span>
							<span class="font-medium">{{ Number(item.price || 0).toLocaleString('vi-VN') }}</span>
						</div>
					</div>
				</div>
			</div>

			<DialogFooter>
				<Button type="button" variant="outline" @click="emit('update:open', false)">Đóng</Button>
			</DialogFooter>
		</DialogContent>
	</Dialog>
</template>
