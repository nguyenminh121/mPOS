<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, Plus, Save, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';
import ImportProductModal from '@/components/tenant/stocks/modals/ImportProductModal.vue';

type OptionRow = { id: number; name: string };

type VariantRow = {
    id: number;
    product_id: number;
    name: string;
    sku?: string | null;
    barcode?: string | null;
    base_unit?: string | null;
    is_default?: boolean;
    product?: { id: number; name: string };
    stocks?: Array<{
        id?: number;
        warehouse_id: number;
        quantity: number | string;
    }>;
    units?: Array<{
        id?: number;
        uom_id: number;
        conversion_factor: number | string;
        uom?: {
            id: number;
            name: string;
            symbol?: string | null;
        } | null;
    }>;
};

type StockTakeItem = {
    id?: number;
    product_variant_id: number;
    system_qty: number;
    actual_qty: number;
    diff_qty: number;
    counted_qty?: number | string | null;
    count_uom_name?: string | null;
    count_conversion_rate?: number | string | null;
    reason?: string | null;
    product_variant?: VariantRow | null;
};

type StockTakePayload = {
    id: number;
    code: string;
    warehouse_id: number;
    status: string;
    note?: string | null;
    items: StockTakeItem[];
};

type ProductPickerOption = {
    product_id: number;
    product_name: string;
    variant_id: number;
    variant_name: string;
    sku?: string | null;
    barcode?: string | null;
    base_unit?: string | null;
};

type ConversionUnit = {
    name: string;
    symbol?: string | null;
    factor: number;
};

type CountUomOption = {
    key: string;
    label: string;
    unitName: string;
    conversionFactor: number;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    stockTake: StockTakePayload | null;
    warehouses: OptionRow[];
    variants: VariantRow[];
}>();

const isEdit = computed(() => props.mode === 'edit');
const statusOptions = ['DRAFT', 'IN_PROGRESS', 'CANCELLED'] as const;

const statusLabelMap: Record<string, string> = {
    DRAFT: 'Nháp',
    IN_PROGRESS: 'Đang kiểm kê',
    COMPLETED: 'Hoàn tất',
    CANCELLED: 'Đã hủy',
};

function displayStatusLabel(status: string): string {
    return statusLabelMap[String(status).toUpperCase()] ?? statusLabelMap.DRAFT;
}

function variantById(variantId: number | null): VariantRow | null {
    if (variantId === null) return null;
    return props.variants.find((item) => item.id === variantId) ?? null;
}

function currentStockInBase(variant: VariantRow, warehouseId: number | null): number {
    const stocks = variant.stocks ?? [];
    if (stocks.length === 0) return 0;

    return stocks
        .filter((stock) => warehouseId === null || Number(stock.warehouse_id) === warehouseId)
        .reduce((sum, stock) => sum + Number(stock.quantity ?? 0), 0);
}

function conversionUnitsForVariant(variant: VariantRow): ConversionUnit[] {
    const units: ConversionUnit[] = [];

    const baseUnit = String(variant.base_unit ?? '').trim();
    if (baseUnit !== '') {
        units.push({
            name: baseUnit,
            symbol: null,
            factor: 1,
        });
    }

    for (const unit of variant.units ?? []) {
        const factor = Number(unit.conversion_factor ?? 1);
        if (!Number.isFinite(factor) || factor <= 0) continue;

        const unitName = String(unit.uom?.name ?? '').trim();
        if (unitName === '') continue;

        units.push({
            name: unitName,
            symbol: unit.uom?.symbol ?? null,
            factor,
        });
    }

    return units.sort((left, right) => right.factor - left.factor);
}

function stockBreakdown(variant: VariantRow, quantity: number): string {
    const units = conversionUnitsForVariant(variant);
    if (units.length === 0) return '';

    let remaining = quantity;
    const parts: string[] = [];

    for (const unit of units) {
        if (unit.factor <= 0) continue;

        const count = Math.floor(remaining / unit.factor);
        if (count <= 0) continue;

        parts.push(`${new Intl.NumberFormat('vi-VN').format(count)} ${unit.name}`);
        remaining -= count * unit.factor;
    }

    return parts.join(' + ');
}

function countUomOptions(variantId: number | null): CountUomOption[] {
    const variant = variantById(variantId);
    if (!variant) return [];

    const units = conversionUnitsForVariant(variant);

    const seen = new Set<string>();
    const options: CountUomOption[] = [];

    for (const unit of units) {
        const normalizedName = unit.name.trim();
        if (normalizedName === '' || seen.has(normalizedName)) continue;
        seen.add(normalizedName);

        options.push({
            key: unit.factor === 1 ? 'base' : `uom:${normalizedName}`,
            label: unit.factor === 1
                ? `${normalizedName} (gốc)`
                : `${normalizedName}${unit.symbol ? ` (${unit.symbol})` : ''} (x${unit.factor})`,
            unitName: normalizedName,
            conversionFactor: unit.factor,
        });
    }

    return options;
}

function resolveBestCountOption(variantId: number | null, baseQty: number): CountUomOption | null {
    const options = countUomOptions(variantId);
    if (options.length === 0) return null;

    const exact = options.find((option) => option.conversionFactor > 1 && Number.isInteger(baseQty / option.conversionFactor));
    if (exact) return exact;

    return options.find((option) => option.conversionFactor === 1) ?? options[0];
}

function normalizeCountedQtyText(value: number): string {
    if (!Number.isFinite(value)) return '0';
    return new Intl.NumberFormat('vi-VN', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    }).format(value);
}

function createItemWithResolvedCountMeta(payload: {
    id?: number;
    product_variant_id: number;
    system_qty?: number;
    actual_qty?: number;
    diff_qty?: number;
    counted_qty?: number | string | null;
    count_uom_name?: string | null;
    count_conversion_rate?: number | string | null;
    reason?: string | null;
}) {
    const variant = variantById(payload.product_variant_id);

    const fallbackSystemQty = variant ? currentStockInBase(variant, props.stockTake?.warehouse_id ?? null) : 0;
    const systemQty = Number(payload.system_qty ?? fallbackSystemQty ?? 0);
    const actualQty = Number(payload.actual_qty ?? 0);

    const bestOption = resolveBestCountOption(payload.product_variant_id, actualQty);

    const requestedName = String(payload.count_uom_name ?? '').trim();
    const requestedRate = Number(payload.count_conversion_rate ?? 0);

    const resolvedOption = requestedName !== '' && requestedRate > 0
        ? countUomOptions(payload.product_variant_id).find((option) => option.unitName === requestedName && option.conversionFactor === requestedRate) ?? bestOption
        : bestOption;

    const conversionFactor = Math.max(1, Number(resolvedOption?.conversionFactor ?? 1));
    const countedQty = payload.counted_qty !== null && payload.counted_qty !== undefined
        ? Number(payload.counted_qty)
        : (conversionFactor > 0 ? Number((actualQty / conversionFactor).toFixed(3)) : actualQty);

    const normalizedCountedQty = Number.isFinite(countedQty) ? countedQty : 0;

    return {
        id: payload.id,
        product_variant_id: payload.product_variant_id,
        system_qty: Number.isFinite(systemQty) ? systemQty : 0,
        actual_qty: Number.isFinite(actualQty) ? actualQty : 0,
        diff_qty: Number(payload.diff_qty ?? 0),
        counted_qty: Math.max(0, normalizedCountedQty),
        count_uom_name: resolvedOption?.unitName ?? variant?.base_unit ?? null,
        count_conversion_rate: conversionFactor,
        selected_count_uom_key: resolvedOption?.key ?? 'base',
        reason: payload.reason ?? '',
    };
}

const form = useForm({
    warehouse_id: props.stockTake?.warehouse_id ?? null,
    status: (props.stockTake?.status ?? 'IN_PROGRESS') as 'DRAFT' | 'IN_PROGRESS' | 'CANCELLED',
    note: props.stockTake?.note ?? '',
    items: (props.stockTake?.items ?? []).map((item) =>
        createItemWithResolvedCountMeta({
            id: item.id,
            product_variant_id: item.product_variant_id,
            system_qty: item.system_qty,
            actual_qty: item.actual_qty,
            diff_qty: item.diff_qty,
            counted_qty: item.counted_qty,
            count_uom_name: item.count_uom_name,
            count_conversion_rate: item.count_conversion_rate,
            reason: item.reason ?? '',
        }),
    ),
});

const importProductModalOpen = ref(false);

const warehouseLabel = computed(() => {
    if (form.warehouse_id === null) return 'Chọn kho';
    return props.warehouses.find((item) => item.id === form.warehouse_id)?.name ?? 'Chọn kho';
});

const statusLabel = computed(() => statusLabelMap[String(form.status).toUpperCase()] ?? statusLabelMap.IN_PROGRESS);

const selectedVariantIds = computed(() =>
    form.items
        .map((item) => item.product_variant_id)
        .filter((value): value is number => typeof value === 'number'),
);

const productPickerOptions = computed<ProductPickerOption[]>(() => {
    const grouped = new Map<number, VariantRow[]>();

    for (const variant of props.variants) {
        if (!variant.product_id) continue;
        const list = grouped.get(variant.product_id) ?? [];
        list.push(variant);
        grouped.set(variant.product_id, list);
    }

    const result: ProductPickerOption[] = [];

    for (const [productId, variants] of grouped.entries()) {
        const defaultVariant = variants.find((variant) => variant.is_default === true) ?? variants[0];
        if (!defaultVariant) continue;

        result.push({
            product_id: productId,
            product_name: defaultVariant.product?.name ?? `Sản phẩm #${productId}`,
            variant_id: defaultVariant.id,
            variant_name: defaultVariant.name,
            sku: defaultVariant.sku ?? null,
            barcode: defaultVariant.barcode ?? null,
            base_unit: defaultVariant.base_unit ?? null,
        });
    }

    return result.sort((left, right) => left.product_name.localeCompare(right.product_name, 'vi'));
});

const openImportProductModal = () => {
    importProductModalOpen.value = true;
};

const itemVariantMeta = (item: typeof form.items[number]) => {
    const variant = variantById(item.product_variant_id);
    if (!variant) return null;

    const productName = variant.product?.name ?? `Sản phẩm #${variant.product_id}`;
    const sku = variant.sku || '-';
    const barcode = variant.barcode || '-';

    return {
        productName,
        variantName: variant.name || '-',
        sku,
        barcode,
    };
};

const itemCountUomLabel = (item: typeof form.items[number]): string => {
    const options = countUomOptions(item.product_variant_id);
    if (options.length === 0) return 'Không có UOM';

    return options.find((option) => option.key === item.selected_count_uom_key)?.label ?? options[0].label;
};

const refreshItemFromCount = (item: typeof form.items[number], refreshSystemQty = false) => {
    const variant = variantById(item.product_variant_id);
    if (!variant) return;

    if (refreshSystemQty) {
        item.system_qty = currentStockInBase(variant, form.warehouse_id);
    }

    const countedQty = Math.max(0, Number(item.counted_qty ?? 0));
    const conversionRate = Math.max(1, Number(item.count_conversion_rate ?? 1));

    const baseQty = Math.round(countedQty * conversionRate);

    item.actual_qty = Math.max(0, baseQty);
    item.diff_qty = item.actual_qty - item.system_qty;
};

const onCountUomChange = (item: typeof form.items[number], selectedKey: string) => {
    const selectedOption = countUomOptions(item.product_variant_id).find((option) => option.key === selectedKey);
    if (!selectedOption) return;

    item.selected_count_uom_key = selectedOption.key;
    item.count_uom_name = selectedOption.unitName;
    item.count_conversion_rate = selectedOption.conversionFactor;

    if (Number(item.actual_qty ?? 0) > 0) {
        item.counted_qty = Number((Number(item.actual_qty) / selectedOption.conversionFactor).toFixed(3));
    }

    refreshItemFromCount(item, false);
};

const onCountedQtyInput = (item: typeof form.items[number]) => {
    refreshItemFromCount(item, false);
};

const addItemFromProductPicker = (payload: ProductPickerOption) => {
    importProductModalOpen.value = false;

    const systemQty = currentStockInBase(variantById(payload.variant_id) ?? { id: payload.variant_id } as VariantRow, form.warehouse_id);

    const initial = createItemWithResolvedCountMeta({
        product_variant_id: payload.variant_id,
        system_qty: systemQty,
        actual_qty: systemQty,
        diff_qty: 0,
        reason: '',
    });

    form.items.push(initial);
    refreshItemFromCount(initial, true);
};

const removeItem = (index: number) => {
    form.items.splice(index, 1);
};

const syncAllItemsForSubmit = () => {
    for (const item of form.items) {
        refreshItemFromCount(item, true);
    }
};

const updateWarehouse = (warehouseId: number | null) => {
    form.warehouse_id = warehouseId;

    for (const item of form.items) {
        refreshItemFromCount(item, true);
    }
};

const submit = () => {
    syncAllItemsForSubmit();

    if (isEdit.value && props.stockTake) {
        form.put(tenantRoutes.stockTakes.update(props.stockTake.id));
        return;
    }

    form.post(tenantRoutes.stockTakes.store());
};
</script>

<template>
    <Head :title="isEdit ? 'Sửa phiếu kiểm kê' : 'Tạo phiếu kiểm kê'" />
    <TenantLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <section class="rounded-2xl border border-border/70 bg-card/95 p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <Link :href="tenantRoutes.stockTakes.index()" class="rounded-xl border border-border/70 bg-background p-2 transition hover:bg-muted/60">
                            <ArrowLeft class="size-5" />
                        </Link>
                        <div>
                            <h1 class="text-2xl font-semibold tracking-tight">{{ isEdit ? 'Sửa phiếu kiểm kê' : 'Tạo phiếu kiểm kê' }}</h1>
                            <p class="mt-1 text-sm text-muted-foreground">Nhập số lượng kiểm theo UOM và hệ thống tự quy đổi về đơn vị gốc để cân bằng tồn kho.</p>
                        </div>
                    </div>

                    <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60" :disabled="form.processing" @click="submit">
                        <Save class="size-4" />
                        {{ isEdit ? 'Cập nhật phiếu' : 'Tạo phiếu' }}
                    </button>
                </div>
            </section>

            <section class="rounded-2xl border border-border/70 bg-card p-5">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="space-y-1 text-sm">
                        <span class="font-medium">Kho kiểm kê</span>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button type="button" class="inline-flex h-10 w-full items-center justify-between rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring">
                                    <span class="truncate">{{ warehouseLabel }}</span>
                                    <ChevronDown class="ml-2 size-4 text-muted-foreground" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-56">
                                <DropdownMenuLabel>Kho</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuRadioGroup
                                    :model-value="form.warehouse_id === null ? '' : String(form.warehouse_id)"
                                    @update:model-value="(value) => updateWarehouse(value === '' ? null : Number(value))"
                                >
                                    <DropdownMenuRadioItem value="">Chọn kho</DropdownMenuRadioItem>
                                    <DropdownMenuRadioItem v-for="item in warehouses" :key="item.id" :value="String(item.id)">
                                        {{ item.name }}
                                    </DropdownMenuRadioItem>
                                </DropdownMenuRadioGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <p v-if="form.errors.warehouse_id" class="text-xs text-destructive">{{ form.errors.warehouse_id }}</p>
                    </div>

                    <div class="space-y-1 text-sm" v-if="isEdit">
                        <span class="font-medium">Trạng thái</span>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button type="button" class="inline-flex h-10 w-full items-center justify-between rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring">
                                    <span class="truncate">{{ statusLabel }}</span>
                                    <ChevronDown class="ml-2 size-4 text-muted-foreground" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-44">
                                <DropdownMenuLabel>Trạng thái</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuRadioGroup v-model="form.status">
                                    <DropdownMenuRadioItem v-for="status in statusOptions" :key="status" :value="status">
                                            {{ displayStatusLabel(status) }}
                                    </DropdownMenuRadioItem>
                                </DropdownMenuRadioGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <p v-if="form.errors.status" class="text-xs text-destructive">{{ form.errors.status }}</p>
                    </div>

                    <label class="space-y-1 text-sm md:col-span-3">
                        <span class="font-medium">Ghi chú</span>
                        <textarea v-model="form.note" rows="3" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2" placeholder="Mô tả lý do kiểm kê, phạm vi..." />
                        <p v-if="form.errors.note" class="text-xs text-destructive">{{ form.errors.note }}</p>
                    </label>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-border/70 bg-card">
                <div class="flex items-center justify-between border-b border-border/70 px-4 py-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Dòng kiểm kê</h2>
                    <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-border/70 bg-background px-3 py-2 text-sm transition hover:bg-muted/60" @click="openImportProductModal">
                        <Plus class="size-4" />
                        Thêm sản phẩm
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1280px] text-sm">
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="p-3 font-medium">Sản phẩm / Biến thể</th>
                                <th class="p-3 text-right font-medium">Tồn hệ thống</th>
                                <th class="p-3 font-medium">ĐVT kiểm</th>
                                <th class="p-3 text-right font-medium">SL kiểm</th>
                                <th class="p-3 text-right font-medium">Quy đổi gốc</th>
                                <th class="p-3 text-right font-medium">Chênh lệch</th>
                                <th class="p-3 font-medium">Lý do</th>
                                <th class="p-3 text-center font-medium">Xóa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="form.items.length === 0">
                                <td colspan="8" class="p-8 text-center text-muted-foreground">Chưa có dòng kiểm kê nào.</td>
                            </tr>
                            <tr v-for="(item, index) in form.items" :key="item.id ?? `new-${index}`" class="border-t align-top">
                                <td class="p-3">
                                    <div v-if="itemVariantMeta(item)" class="space-y-1">
                                        <p class="font-medium">{{ itemVariantMeta(item)?.productName }}</p>
                                        <p class="text-xs text-muted-foreground">{{ itemVariantMeta(item)?.variantName }}</p>
                                        <p class="text-xs text-muted-foreground">SKU: {{ itemVariantMeta(item)?.sku }} • Barcode: {{ itemVariantMeta(item)?.barcode }}</p>
                                    </div>
                                    <p v-else class="text-muted-foreground">Biến thể không hợp lệ</p>
                                </td>

                                <td class="p-3 text-right">
                                    <p class="font-semibold">{{ item.system_qty }}</p>
                                    <p class="text-xs text-muted-foreground" v-if="variantById(item.product_variant_id)">
                                        {{ stockBreakdown(variantById(item.product_variant_id)!, item.system_qty) || '—' }}
                                    </p>
                                </td>

                                <td class="p-3">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <button type="button" class="inline-flex h-9 w-full items-center justify-between rounded-lg border border-border/70 bg-background px-2.5 py-1.5 text-left text-sm outline-none transition focus:ring-2 focus:ring-ring">
                                                <span class="truncate">{{ itemCountUomLabel(item) }}</span>
                                                <ChevronDown class="ml-2 size-4 shrink-0 text-muted-foreground" />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-56">
                                            <DropdownMenuLabel>Đơn vị kiểm kê</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuRadioGroup
                                                :model-value="item.selected_count_uom_key"
                                                @update:model-value="(value) => onCountUomChange(item, String(value))"
                                            >
                                                <DropdownMenuRadioItem
                                                    v-for="option in countUomOptions(item.product_variant_id)"
                                                    :key="option.key"
                                                    :value="option.key"
                                                >
                                                    {{ option.label }}
                                                </DropdownMenuRadioItem>
                                            </DropdownMenuRadioGroup>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>

                                <td class="p-3 text-right">
                                    <input
                                        v-model.number="item.counted_qty"
                                        type="number"
                                        min="0"
                                        step="0.001"
                                        class="w-28 rounded-lg border border-border/70 bg-background px-2 py-1 text-right"
                                        @input="onCountedQtyInput(item)"
                                    />
                                    <p class="mt-1 text-xs text-muted-foreground">{{ normalizeCountedQtyText(Number(item.counted_qty || 0)) }} {{ item.count_uom_name || '' }}</p>
                                </td>

                                <td class="p-3 text-right">
                                    <p class="font-semibold">{{ item.actual_qty }}</p>
                                    <p class="text-xs text-muted-foreground" v-if="variantById(item.product_variant_id)">
                                        {{ variantById(item.product_variant_id)?.base_unit || '' }}
                                    </p>
                                </td>

                                <td class="p-3 text-right">
                                    <span :class="Number(item.diff_qty) === 0 ? 'text-muted-foreground' : Number(item.diff_qty) > 0 ? 'text-primary' : 'text-destructive'" class="font-semibold">
                                        {{ Number(item.diff_qty) > 0 ? '+' : '' }}{{ item.diff_qty }}
                                    </span>
                                </td>

                                <td class="p-3">
                                    <input v-model="item.reason" type="text" class="w-full rounded-lg border border-border/70 bg-background px-2 py-1" placeholder="Lý do chênh lệch" />
                                </td>

                                <td class="p-3 text-center">
                                    <button type="button" class="rounded-md border border-border/70 p-1.5 text-destructive hover:bg-destructive/10" @click="removeItem(index)">
                                        <Trash2 class="size-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-border/70 px-4 py-3 text-xs text-muted-foreground">
                    Hệ thống quy đổi tự động về đơn vị gốc theo UOM đã chọn (ví dụ: 4 Thùng × 24 = 96 Lon).
                </div>
            </section>
        </div>

        <ImportProductModal
            :open="importProductModalOpen"
            :products="productPickerOptions"
            :selected-variant-ids="selectedVariantIds"
            @update:open="(value) => (importProductModalOpen = value)"
            @select="addItemFromProductPicker"
        />
    </TenantLayout>
</template>
