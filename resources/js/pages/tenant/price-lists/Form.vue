<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, MoreHorizontal, Plus, Save, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuLabel,
	DropdownMenuSeparator,
	DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type VariantOption = {
	id: number;
	product_name?: string | null;
	name: string;
	sku?: string | null;
	base_unit: string;
	default_price: number;
	unit_names: string[];
};

type PriceItem = {
	id?: number;
	product_variant_id: number;
	unit_name: string;
	price: number;
	min_quantity: number;
};

type PriceListPayload = {
	id: number;
	code: string;
	name: string;
	is_default: boolean;
	is_active: boolean;
	priority: number;
	description?: string | null;
	start_date?: string | null;
	end_date?: string | null;
	items: PriceItem[];
};

type PriceItemForm = {
	product_variant_id: number | null;
	unit_name: string;
	price: number;
	min_quantity: number;
};

const props = defineProps<{
	mode: 'create' | 'edit';
	priceList: PriceListPayload | null;
	variantOptions: VariantOption[];
}>();

const isEdit = computed(() => props.mode === 'edit');

const initialItems: PriceItemForm[] = (props.priceList?.items ?? []).map((item) => ({
	product_variant_id: item.product_variant_id,
	unit_name: item.unit_name,
	price: item.price,
	min_quantity: item.min_quantity,
}));

const form = useForm({
	code: props.priceList?.code ?? '',
	name: props.priceList?.name ?? '',
	is_default: props.priceList?.is_default ?? false,
	is_active: props.priceList?.is_active ?? true,
	priority: props.priceList?.priority ?? 0,
	description: props.priceList?.description ?? '',
	start_date: props.priceList?.start_date ?? '',
	end_date: props.priceList?.end_date ?? '',
	items: initialItems,
});

const variantMap = computed(() => {
	return new Map<number, VariantOption>(props.variantOptions.map((item) => [item.id, item]));
});

const addEmptyItem = () => {
	form.items.push({
		product_variant_id: null,
		unit_name: '',
		price: 0,
		min_quantity: 1,
	});
};

const removeItem = (index: number) => {
	form.items.splice(index, 1);
};

const clearItems = () => {
	form.items = [];
};

const onVariantChange = (index: number) => {
	const row = form.items[index];
	if (!row || row.product_variant_id === null) return;

	const option = variantMap.value.get(row.product_variant_id);
	if (!option) return;

	if (!row.unit_name || !option.unit_names.includes(row.unit_name)) {
		row.unit_name = option.unit_names[0] ?? option.base_unit;
	}

	if (!row.price || row.price <= 0) {
		row.price = option.default_price;
	}
};

const selectVariant = (index: number, variantId: number | null) => {
	const row = form.items[index];
	if (!row) return;

	row.product_variant_id = variantId;

	if (variantId === null) {
		row.unit_name = '';
		return;
	}

	onVariantChange(index);
};

const selectedVariantLabel = (index: number): string => {
	const row = form.items[index];
	if (!row || row.product_variant_id === null) {
		return 'Chọn biến thể';
	}

	const variant = variantMap.value.get(row.product_variant_id);
	if (!variant) return 'Chọn biến thể';

	return `${variant.product_name ?? '-'} - ${variant.name}${variant.sku ? ` (${variant.sku})` : ''}`;
};

const unitOptionsForRow = (index: number): string[] => {
	const row = form.items[index];
	if (!row || row.product_variant_id === null) return [];

	return variantMap.value.get(row.product_variant_id)?.unit_names ?? [];
};

const selectUnit = (index: number, unitName: string) => {
	const row = form.items[index];
	if (!row) return;

	row.unit_name = unitName;
};

const selectedUnitLabel = (index: number): string => {
	const row = form.items[index];
	if (!row || !row.unit_name) return 'Chọn UOM';

	return row.unit_name;
};

const addAllVariants = () => {
	const existing = new Set(
		form.items
			.filter((item) => item.product_variant_id !== null)
			.map((item) => `${item.product_variant_id}|${item.unit_name}|${item.min_quantity}`),
	);

	for (const variant of props.variantOptions) {
		for (const unitName of variant.unit_names) {
			const key = `${variant.id}|${unitName}|1`;
			if (existing.has(key)) continue;

			form.items.push({
				product_variant_id: variant.id,
				unit_name: unitName,
				price: variant.default_price,
				min_quantity: 1,
			});
			existing.add(key);
		}
	}
};

const submit = () => {
	form.transform((data) => ({
		...data,
		items: data.items
			.filter((item) => item.product_variant_id !== null)
			.map((item) => ({
				product_variant_id: Number(item.product_variant_id),
				unit_name: item.unit_name,
				price: Number(item.price),
				min_quantity: Number(item.min_quantity || 1),
			})),
	}));

	if (isEdit.value && props.priceList) {
		form.put(tenantRoutes.priceLists.update(props.priceList.id));
		return;
	}

	form.post(tenantRoutes.priceLists.store());
};
</script>

<template>
	<Head :title="isEdit ? 'Sửa bảng giá' : 'Thêm bảng giá'" />
	<TenantLayout>
		<div class="mx-auto max-w-6xl space-y-6">
			<div class="flex items-center gap-3">
				<Button as-child variant="outline" size="icon">
					<Link :href="tenantRoutes.priceLists.index()"><ArrowLeft class="size-5" /></Link>
				</Button>
				<div>
					<h1 class="text-2xl font-semibold tracking-tight">{{ isEdit ? 'Sửa bảng giá' : 'Thêm bảng giá' }}</h1>
					<p class="text-sm text-muted-foreground">Thiết lập giá riêng theo biến thể/UOM; có thể gán thủ công hoặc nạp toàn bộ sản phẩm.</p>
				</div>
			</div>

			<form class="space-y-5" @submit.prevent="submit">
				<section class="grid grid-cols-1 gap-4 rounded-2xl border border-border/70 bg-card p-5 md:grid-cols-2">
					<div>
						<Label class="mb-2">Mã bảng giá *</Label>
						<Input v-model="form.code" />
						<p v-if="form.errors.code" class="mt-1 text-xs text-destructive">{{ form.errors.code }}</p>
					</div>
					<div>
						<Label class="mb-2">Tên bảng giá *</Label>
						<Input v-model="form.name" />
						<p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
					</div>
					<div>
						<Label class="mb-2">Độ ưu tiên</Label>
						<Input v-model.number="form.priority" type="number" min="0" />
					</div>
					<div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
						<Label class="inline-flex items-center gap-2 text-sm"><input v-model="form.is_default" type="checkbox" class="rounded" /> Bảng giá mặc định</Label>
						<Label class="inline-flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded" /> Đang hoạt động</Label>
					</div>
					<div>
						<Label class="mb-2">Bắt đầu</Label>
						<Input v-model="form.start_date" type="date" />
					</div>
					<div>
						<Label class="mb-2">Kết thúc</Label>
						<Input v-model="form.end_date" type="date" />
						<p v-if="form.errors.end_date" class="mt-1 text-xs text-destructive">{{ form.errors.end_date }}</p>
					</div>
					<div class="md:col-span-2">
						<Label class="mb-2">Mô tả</Label>
						<textarea v-model="form.description" rows="2" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm" />
					</div>
				</section>

				<section class="space-y-3 rounded-2xl border border-border/70 bg-card p-5">
					<div class="flex flex-wrap items-center justify-between gap-2">
						<div>
							<h2 class="text-base font-semibold">Chi tiết giá theo sản phẩm / biến thể / UOM</h2>
							<p class="text-xs text-muted-foreground">Đang có {{ form.items.length }} dòng giá</p>
						</div>
						<div class="flex items-center gap-2">
							<Button type="button" variant="outline" @click="addEmptyItem"><Plus class="size-4" />Thêm dòng</Button>
							<DropdownMenu>
								<DropdownMenuTrigger as-child>
									<Button type="button" variant="outline" size="icon">
										<MoreHorizontal class="size-4" />
									</Button>
								</DropdownMenuTrigger>
								<DropdownMenuContent align="end" class="w-56">
									<DropdownMenuLabel>Hành động nhanh</DropdownMenuLabel>
									<DropdownMenuSeparator />
									<DropdownMenuItem @click="addAllVariants">
										<ChevronDown class="size-4 rotate-180" />
										Nạp tất cả sản phẩm
									</DropdownMenuItem>
									<DropdownMenuItem @click="clearItems">
										<Trash2 class="size-4" />
										Xóa toàn bộ dòng giá
									</DropdownMenuItem>
								</DropdownMenuContent>
							</DropdownMenu>
						</div>
					</div>

					<div class="overflow-x-auto">
						<table class="w-full text-sm">
							<thead class="bg-muted/40 text-left">
								<tr>
									<th class="p-2 font-medium">Biến thể sản phẩm</th>
									<th class="p-2 font-medium">UOM</th>
									<th class="p-2 font-medium">Giá</th>
									<th class="p-2 font-medium">SL tối thiểu</th>
									<th class="p-2 text-center font-medium">Xóa</th>
								</tr>
							</thead>
							<tbody>
								<tr v-if="form.items.length === 0">
									<td colspan="5" class="p-4 text-center text-muted-foreground">Chưa có dòng giá nào. Chọn "Thêm dòng" hoặc "Nạp tất cả sản phẩm".</td>
								</tr>
								<tr v-for="(item, index) in form.items" :key="index" class="border-t">
									<td class="p-2">
										<DropdownMenu>
											<DropdownMenuTrigger as-child>
												<Button type="button" variant="outline" class="w-full justify-between text-left font-normal">
													<span class="truncate">{{ selectedVariantLabel(index) }}</span>
													<ChevronDown class="size-4 text-muted-foreground" />
												</Button>
											</DropdownMenuTrigger>
											<DropdownMenuContent align="start" class="max-h-72 w-[var(--reka-dropdown-menu-trigger-width)] overflow-y-auto">
												<DropdownMenuLabel>Biến thể sản phẩm</DropdownMenuLabel>
												<DropdownMenuSeparator />
												<DropdownMenuItem @click="selectVariant(index, null)">Chọn biến thể</DropdownMenuItem>
												<DropdownMenuItem v-for="variant in variantOptions" :key="variant.id" @click="selectVariant(index, variant.id)">
													{{ variant.product_name }} - {{ variant.name }} {{ variant.sku ? `(${variant.sku})` : '' }}
												</DropdownMenuItem>
											</DropdownMenuContent>
										</DropdownMenu>
									</td>
									<td class="p-2">
										<DropdownMenu>
											<DropdownMenuTrigger as-child>
												<Button type="button" variant="outline" class="w-full justify-between text-left font-normal" :disabled="item.product_variant_id === null">
													<span class="truncate">{{ selectedUnitLabel(index) }}</span>
													<ChevronDown class="size-4 text-muted-foreground" />
												</Button>
											</DropdownMenuTrigger>
											<DropdownMenuContent align="start" class="max-h-64 w-[var(--reka-dropdown-menu-trigger-width)] overflow-y-auto">
												<DropdownMenuLabel>Đơn vị tính (UOM)</DropdownMenuLabel>
												<DropdownMenuSeparator />
												<DropdownMenuItem @click="selectUnit(index, '')">Chọn UOM</DropdownMenuItem>
												<DropdownMenuItem v-for="unit in unitOptionsForRow(index)" :key="unit" @click="selectUnit(index, unit)">
													{{ unit }}
												</DropdownMenuItem>
											</DropdownMenuContent>
										</DropdownMenu>
									</td>
									<td class="p-2"><Input v-model.number="item.price" type="number" min="0" /></td>
									<td class="p-2"><Input v-model.number="item.min_quantity" type="number" min="1" /></td>
									<td class="p-2 text-center">
										<Button type="button" variant="outline" size="icon-sm" @click="removeItem(index)"><Trash2 class="size-4" /></Button>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
					<div class="pt-1">
						<Badge variant="secondary">Tổng dòng giá: {{ form.items.length }}</Badge>
					</div>
					<p v-if="form.errors.items" class="text-xs text-destructive">{{ form.errors.items }}</p>
				</section>

				<div class="flex items-center gap-2">
					<Button type="submit" :disabled="form.processing">
						<Save class="size-4" />
						{{ isEdit ? 'Lưu cập nhật' : 'Tạo bảng giá' }}
					</Button>
					<Button as-child variant="outline">
						<Link :href="tenantRoutes.priceLists.index()">Hủy</Link>
					</Button>
				</div>
			</form>
		</div>
	</TenantLayout>
</template>
