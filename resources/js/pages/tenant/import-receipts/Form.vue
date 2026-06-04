<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, Save, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import UOMFormModal from '@/components/tenant/uoms/modals/UOMFormModal.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { filterAndRankProductVariants } from '@/composables/products/productSearch';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { productPublicRef } from '@/lib/productPublicRef';
import { tenantRoutes } from '@/lib/tenantRoutes';

type OptionRow = { id: number; name: string };

type UomOption = {
    id: number;
    name: string;
    symbol?: string | null;
    base_uom_id?: number | null;
    is_active: boolean;
};

type VariantRow = {
    id: number;
    product_id: number;
    name: string;
    sku?: string | null;
    barcode?: string | null;
    base_unit?: string | null;
    is_default?: boolean;
    price?: number | string | null;
    wholesale_price?: number | string | null;
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

type ItemRow = {
    id?: number;
    product_variant_id: number | null;
    unit_name?: string | null;
    conversion_rate?: number | null;
    quantity: number;
    quantity_in_base: number;
    import_price: number;
    tax_rate: number;
    note?: string | null;
};

type ReceiptPayload = {
    id: number;
    supplier_id?: number | null;
    warehouse_id?: number | null;
    status: string;
    import_date?: string | null;
    note?: string | null;
    items: ItemRow[];
};

const props = defineProps<{
    mode: 'create' | 'edit';
    receipt: ReceiptPayload | null;
    warehouses: OptionRow[];
    suppliers: OptionRow[];
    variants: VariantRow[];
    uoms: UomOption[];
}>();

const isEdit = computed(() => props.mode === 'edit');

const toLocalDateTime = (value?: string | null) => {
    if (!value) return '';
    return value.slice(0, 16);
};

const form = useForm({
    supplier_id: props.receipt?.supplier_id ?? null,
    warehouse_id: props.receipt?.warehouse_id ?? null,
    status: props.receipt?.status ?? 'COMPLETED',
    import_date: toLocalDateTime(props.receipt?.import_date),
    note: props.receipt?.note ?? '',
    items: (props.receipt?.items?.length ? props.receipt.items : []).map((item) => ({
        id: item.id,
        product_id: null as number | null,
        product_variant_id: item.product_variant_id,
        selected_uom_key: '' as string,
        unit_name: item.unit_name ?? null,
        conversion_rate: item.conversion_rate ?? 1,
        quantity: Number(item.quantity ?? 1),
        quantity_in_base: Number(item.quantity_in_base ?? 1),
        import_price: Number(item.import_price ?? 0),
        tax_rate: Number(item.tax_rate ?? 0),
        note: item.note ?? '',
    })),
});

const statusRank: Record<string, number> = {
    DRAFT: 1,
    PENDING: 2,
    APPROVED: 3,
    COMPLETED: 4,
    CANCELLED: 5,
};
const statusLabelMap: Record<string, string> = {
    DRAFT: 'Nháp',
    PENDING: 'Chờ duyệt',
    APPROVED: 'Đã duyệt',
    COMPLETED: 'Hoàn tất',
    CANCELLED: 'Đã hủy',
};

const statusOptions = computed<string[]>(() => {
    if (!isEdit.value || !props.receipt) {
        return ['DRAFT', 'PENDING', 'APPROVED', 'COMPLETED'];
    }

    const currentStatus = String(props.receipt.status ?? 'DRAFT').toUpperCase();
    if (currentStatus === 'CANCELLED') {
        return ['CANCELLED'];
    }

    if (currentStatus === 'COMPLETED') {
        return ['COMPLETED', 'CANCELLED'];
    }

    const currentRank = statusRank[currentStatus] ?? statusRank.DRAFT;
    return ['DRAFT', 'PENDING', 'APPROVED', 'COMPLETED']
        .filter((status) => (statusRank[status] ?? 0) >= currentRank);
});
const localUoms = ref<UomOption[]>([...props.uoms]);
const uomModalOpen = ref(false);
const selectedProductForUomId = ref<number | null>(null);
const productSearchQuery = ref('');

type ProductPickerOption = {
    product_id: number;
    product_name: string;
    variant_id: number;
    variant_name: string;
    sku?: string | null;
    barcode?: string | null;
    base_unit?: string | null;
};

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

const selectedVariantIds = computed(() =>
    form.items
        .map((item) => item.product_variant_id)
        .filter((value): value is number => typeof value === 'number'),
);

const filteredProductPickerOptions = computed<ProductPickerOption[]>(() => {
    const selectedIds = new Set(selectedVariantIds.value);
    const source = productPickerOptions.value.filter((option) => !selectedIds.has(option.variant_id));

    return filterAndRankProductVariants(
        source.map((option) => ({
            ...option,
            id: option.variant_id,
            name: option.variant_name,
            product_name: option.product_name,
        })),
        productSearchQuery.value,
        productSearchQuery.value.trim() === '' ? 12 : 20,
    ).map((item) => ({
        ...item,
        variant_name: item.name,
    }));
});

const warehouseLabel = computed(() => {
    if (form.warehouse_id === null) return 'Chọn kho';
    return props.warehouses.find((item) => item.id === form.warehouse_id)?.name ?? 'Chọn kho';
});

const supplierLabel = computed(() => {
    if (form.supplier_id === null) return 'Chọn nhà cung cấp';
    return props.suppliers.find((item) => item.id === form.supplier_id)?.name ?? 'Chọn nhà cung cấp';
});

const statusLabel = computed(() => {
    const rawStatus = String(form.status ?? '').toUpperCase();
    if (rawStatus !== '' && statusLabelMap[rawStatus]) {
        return statusLabelMap[rawStatus];
    }

    if (rawStatus !== '') {
        return rawStatus;
    }

    return statusLabelMap.COMPLETED;
});

const addItemFromProductPicker = (payload: ProductPickerOption) => {
    const newItem = {
        id: undefined,
        product_id: payload.product_id,
        product_variant_id: payload.variant_id,
        selected_uom_key: '',
        unit_name: null,
        conversion_rate: 1,
        quantity: 1,
        quantity_in_base: 1,
        import_price: 0,
        tax_rate: 0,
        note: '',
    };

    form.items.push(newItem);
    syncItemDerived(newItem);
};

const addItemFromSearch = (payload: ProductPickerOption) => {
    addItemFromProductPicker(payload);
    productSearchQuery.value = '';
};

const removeItem = (index: number) => {
    form.items.splice(index, 1);
};

const itemTotal = (item: typeof form.items[number]) => {
    const quantityInBase = Number(item.quantity_in_base || 0);
    const importPrice = Number(item.import_price || 0);
    const taxRate = Number(item.tax_rate || 0);
    const lineAmount = quantityInBase * importPrice;
    const taxAmount = (lineAmount * taxRate) / 100;
    return lineAmount + taxAmount;
};

const grandTotal = computed(() => form.items.reduce((sum, item) => sum + itemTotal(item), 0));

const formatMoney = (value: number) => new Intl.NumberFormat('vi-VN').format(value);

const variantLabel = (variant: VariantRow) => {
    const productName = variant.product?.name ?? `Sản phẩm #${variant.product_id}`;
    return {
        productName,
        variantName: variant.name || '-',
        sku: variant.sku || '-',
        barcode: variant.barcode || '-',
    };
};

const itemVariantMeta = (item: typeof form.items[number]) => {
    const variant = variantById(item.product_variant_id);
    if (!variant) return null;
    return variantLabel(variant);
};

const variantById = (variantId: number | null) => {
    if (variantId === null) return null;
    return props.variants.find((item) => item.id === variantId) ?? null;
};

type ItemUomOption = {
    key: string;
    label: string;
    unitName: string;
    conversionFactor: number;
};

const itemUomOptions = (variantId: number | null): ItemUomOption[] => {
    const variant = variantById(variantId);
    if (!variant) return [];

    const result: ItemUomOption[] = [];

    const baseUnit = (variant.base_unit ?? '').trim();
    if (baseUnit !== '') {
        result.push({
            key: 'base',
            label: `${baseUnit} (gốc)`,
            unitName: baseUnit,
            conversionFactor: 1,
        });
    }

    for (const unit of variant.units ?? []) {
        if (!unit.uom) continue;

        const conversionFactor = Number(unit.conversion_factor ?? 1);
        if (!Number.isFinite(conversionFactor) || conversionFactor <= 0) continue;

        const unitName = unit.uom.name;
        const symbol = unit.uom.symbol ? ` (${unit.uom.symbol})` : '';

        result.push({
            key: `uom:${unit.uom.id}`,
            label: `${unitName}${symbol}`,
            unitName,
            conversionFactor,
        });
    }

    return result;
};

const itemUomLabel = (item: typeof form.items[number]) => {
    if (item.product_variant_id === null) return 'Chọn biến thể trước';

    const options = itemUomOptions(item.product_variant_id);
    if (options.length === 0) return 'Chưa có ĐVT cấu hình';

    const selected = options.find((option) => option.key === item.selected_uom_key);
    return selected ? selected.label : 'Chọn ĐVT';
};

type ConversionUnit = {
    name: string;
    symbol?: string | null;
    factor: number;
};

const conversionUnitsForVariant = (variant: VariantRow): ConversionUnit[] => {
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
};

const currentStockInBase = (variant: VariantRow, warehouseId: number | null): number => {
    const stocks = variant.stocks ?? [];
    if (stocks.length === 0) return 0;

    return stocks
        .filter((stock) => warehouseId === null || Number(stock.warehouse_id) === warehouseId)
        .reduce((sum, stock) => sum + Math.max(0, Number(stock.quantity ?? 0)), 0);
};

const stockPrimaryLabelForVariant = (variant: VariantRow): string => {
    const stockBase = currentStockInBase(variant, form.warehouse_id);
    const unit = highestDisplayUnit(variant, stockBase);

    if (!unit) {
        return `${stockBase}`;
    }

    const quantity = unit.factor > 0 ? Math.floor(stockBase / unit.factor) : stockBase;
    const displayName = unit.symbol ? `${unit.name} (${unit.symbol})` : unit.name;

    return `${quantity} ${displayName}`;
};

const stockBreakdownLabelForVariant = (variant: VariantRow): string => {
    const units = conversionUnitsForVariant(variant);
    if (units.length === 0) return '';

    let remainder = currentStockInBase(variant, form.warehouse_id);
    const parts: string[] = [];

    for (const unit of units) {
        if (unit.factor <= 0) continue;

        const count = Math.floor(remainder / unit.factor);
        if (count <= 0) continue;

        parts.push(`${count} ${unit.symbol || unit.name}`);
        remainder -= count * unit.factor;
    }

    if (parts.length === 0) {
        const baseUnit = units[units.length - 1];
        return `${Math.max(0, remainder)} ${baseUnit.symbol || baseUnit.name}`;
    }

    return parts.join(' + ');
};

const productOptionStockPrimaryLabel = (option: ProductPickerOption): string => {
    const variant = variantById(option.variant_id);
    if (!variant) return '0';

    return stockPrimaryLabelForVariant(variant);
};

const productOptionStockBreakdownLabel = (option: ProductPickerOption): string => {
    const variant = variantById(option.variant_id);
    if (!variant) return '';

    return stockBreakdownLabelForVariant(variant);
};

const highestDisplayUnit = (variant: VariantRow, baseQuantity: number): ConversionUnit | null => {
    const sortedUnits = conversionUnitsForVariant(variant);
    if (sortedUnits.length === 0) return null;

    return sortedUnits.find((unit) => baseQuantity >= unit.factor) ?? sortedUnits[sortedUnits.length - 1];
};

const stockPrimaryLabel = (item: typeof form.items[number]): string => {
    const variant = variantById(item.product_variant_id);
    if (!variant) return '-';

    return stockPrimaryLabelForVariant(variant);
};

const stockBreakdownLabel = (item: typeof form.items[number]): string => {
    const variant = variantById(item.product_variant_id);
    if (!variant) return '';

    return stockBreakdownLabelForVariant(variant);
};

const syncItemDerived = (item: typeof form.items[number]) => {
    const options = itemUomOptions(item.product_variant_id);

    if (options.length === 0) {
        item.selected_uom_key = '';
        item.unit_name = null;
        item.conversion_rate = 1;
        item.quantity_in_base = Math.max(1, Number(item.quantity || 1));
        return;
    }

    let selected = options.find((option) => option.key === item.selected_uom_key);
    if (!selected) {
        selected = options[0];
        item.selected_uom_key = selected.key;
    }

    item.unit_name = selected.unitName;
    item.conversion_rate = selected.conversionFactor;
    item.quantity_in_base = Math.max(1, Math.round(Number(item.quantity || 0) * selected.conversionFactor));
};

const normalizeMenuValue = (value: unknown): string => {
    if (typeof value === 'string' || typeof value === 'number' || typeof value === 'bigint') {
        return String(value);
    }

    return '';
};

const onUomChange = (item: typeof form.items[number], value: unknown) => {
    item.selected_uom_key = normalizeMenuValue(value);
    syncItemDerived(item);
};

const onQuantityChange = (item: typeof form.items[number]) => {
    item.quantity = Math.max(1, Number(item.quantity || 1));
    syncItemDerived(item);
};

const openUomModal = (item: typeof form.items[number]) => {
    selectedProductForUomId.value = item.product_id;
    uomModalOpen.value = true;
};

const selectedProductEditHref = computed(() => {
    if (selectedProductForUomId.value === null) return null;

    const firstVariant = props.variants.find((item) => item.product_id === selectedProductForUomId.value);
    if (!firstVariant) return null;

    const ref = productPublicRef({
        id: firstVariant.product_id,
        sku: firstVariant.sku,
        barcode: firstVariant.barcode,
    });

    if (String(ref).trim() === '') return null;

    return tenantRoutes.products.edit(ref);
});

const hydrateItems = () => {
    for (const item of form.items) {
        const variant = variantById(item.product_variant_id);
        item.product_id = variant?.product_id ?? null;

        const options = itemUomOptions(item.product_variant_id);
        if (options.length === 0) {
            item.selected_uom_key = '';
            continue;
        }

        const matched = options.find((option) => {
            const sameName = (item.unit_name ?? '').trim() !== '' && option.unitName === item.unit_name;
            const sameConversion = Number(item.conversion_rate ?? 1) === option.conversionFactor;
            return sameName || sameConversion;
        });

        item.selected_uom_key = matched?.key ?? options[0].key;
        syncItemDerived(item);
    }
};

hydrateItems();

const handleUomSaved = (uom: UomOption) => {
    if (localUoms.value.some((item) => item.id === uom.id)) return;
    localUoms.value.push(uom);
    localUoms.value = [...localUoms.value].sort((a, b) => a.name.localeCompare(b.name, 'vi'));
};

const submit = () => {
    for (const item of form.items) {
        syncItemDerived(item);
    }

    form.transform((data) => ({
        supplier_id: data.supplier_id,
        warehouse_id: data.warehouse_id,
        status: data.status,
        import_date: data.import_date,
        note: data.note,
        items: data.items.map((item) => ({
            id: item.id,
            product_variant_id: item.product_variant_id,
            unit_name: item.unit_name,
            conversion_rate: item.conversion_rate,
            quantity: item.quantity,
            quantity_in_base: item.quantity_in_base,
            import_price: item.import_price,
            tax_rate: item.tax_rate,
            note: item.note,
        })),
    }));

    if (isEdit.value && props.receipt) {
        form.put(tenantRoutes.importReceipts.update(props.receipt.id));
        return;
    }

    form.post(tenantRoutes.importReceipts.store());
};
</script>

<template>
    <Head :title="isEdit ? 'Sửa phiếu nhập' : 'Tạo phiếu nhập'" />
    <TenantLayout>
        <div class="mx-auto max-w-screen-2xl space-y-6">
            <div class="flex items-center gap-3">
                <Link :href="tenantRoutes.importReceipts.index()" class="rounded-xl border border-border/70 bg-background p-2 transition hover:bg-muted/60">
                    <ArrowLeft class="size-5" />
                </Link>
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ isEdit ? 'Sửa phiếu nhập' : 'Tạo phiếu nhập mới' }}</h1>
                    <p class="text-sm text-muted-foreground">Khai báo thông tin kho, nhà cung cấp và danh sách hàng nhập.</p>
                </div>
            </div>

            <form class="space-y-6 rounded-2xl border border-border/70 bg-card p-6" @submit.prevent="submit">
                <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Kho <span class="text-destructive">*</span></label>
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
                                    @update:model-value="(value) => (form.warehouse_id = value === '' ? null : Number(value))"
                                >
                                    <DropdownMenuRadioItem value="">Chọn kho</DropdownMenuRadioItem>
                                    <DropdownMenuRadioItem v-for="item in warehouses" :key="item.id" :value="String(item.id)">
                                        {{ item.name }}
                                    </DropdownMenuRadioItem>
                                </DropdownMenuRadioGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <p v-if="form.errors.warehouse_id" class="mt-1 text-xs text-destructive">{{ form.errors.warehouse_id }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Nhà cung cấp</label>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button type="button" class="inline-flex h-10 w-full items-center justify-between rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring">
                                    <span class="truncate">{{ supplierLabel }}</span>
                                    <ChevronDown class="ml-2 size-4 text-muted-foreground" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-56">
                                <DropdownMenuLabel>Nhà cung cấp</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuRadioGroup
                                    :model-value="form.supplier_id === null ? '' : String(form.supplier_id)"
                                    @update:model-value="(value) => (form.supplier_id = value === '' ? null : Number(value))"
                                >
                                    <DropdownMenuRadioItem value="">Chọn nhà cung cấp</DropdownMenuRadioItem>
                                    <DropdownMenuRadioItem v-for="item in suppliers" :key="item.id" :value="String(item.id)">
                                        {{ item.name }}
                                    </DropdownMenuRadioItem>
                                </DropdownMenuRadioGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <p v-if="form.errors.supplier_id" class="mt-1 text-xs text-destructive">{{ form.errors.supplier_id }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Ngày nhập</label>
                        <input v-model="form.import_date" type="datetime-local" class="h-10 w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring" />
                        <p v-if="form.errors.import_date" class="mt-1 text-xs text-destructive">{{ form.errors.import_date }}</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Trạng thái</label>
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
                                        {{ statusLabelMap[status] ?? status }}
                                    </DropdownMenuRadioItem>
                                </DropdownMenuRadioGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <p v-if="form.errors.status" class="mt-1 text-xs text-destructive">{{ form.errors.status }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Ghi chú</label>
                    <textarea v-model="form.note" rows="3" class="w-full resize-none rounded-xl border border-border/70 bg-background px-3 py-2.5 text-sm outline-none transition focus:ring-2 focus:ring-ring" placeholder="Ghi chú cho phiếu nhập" />
                    <p v-if="form.errors.note" class="mt-1 text-xs text-destructive">{{ form.errors.note }}</p>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Chi tiết hàng nhập</h2>
                    </div>

                    <div class="rounded-xl border border-border/70 bg-card/60 p-3">
                        <label class="mb-1.5 block text-sm font-medium">Tìm hàng hóa để thêm</label>
                        <input
                            v-model="productSearchQuery"
                            type="text"
                            placeholder="Nhập tên/SPU/SKU/barcode..."
                            class="h-10 w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring"
                        />
                        <div class="mt-2 max-h-56 overflow-auto rounded-lg border border-border/60 bg-background">
                            <button
                                v-for="option in filteredProductPickerOptions"
                                :key="`${option.product_id}-${option.variant_id}`"
                                type="button"
                                class="flex w-full items-start justify-between gap-3 border-b border-border/40 px-3 py-2 text-left text-sm transition hover:bg-muted/40 last:border-b-0"
                                @click="addItemFromSearch(option)"
                            >
                                <div class="min-w-0">
                                    <p class="truncate font-medium">{{ option.product_name }}</p>
                                    <p class="truncate text-[13px] font-bold text-muted-foreground">
                                        Tồn hiện tại: {{ productOptionStockPrimaryLabel(option) }}
                                        <span v-if="productOptionStockBreakdownLabel(option)">({{ productOptionStockBreakdownLabel(option) }})</span>
                                    </p>
                                    <p class="truncate text-xs text-muted-foreground">{{ option.variant_name }}</p>
                                </div>
                                <p class="shrink-0 text-[11px] text-muted-foreground">SKU: {{ option.sku || '-' }} • BC: {{ option.barcode || '-' }}</p>
                            </button>
                            <p v-if="filteredProductPickerOptions.length === 0" class="px-3 py-2 text-xs text-muted-foreground">
                                Không tìm thấy hàng hóa phù hợp.
                            </p>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-border/70">
                        <table class="w-full min-w-[1360px] text-sm leading-relaxed">
                            <thead class="bg-muted/40">
                                <tr>
                                    <th class="px-3 py-2.5 text-left font-medium">Hàng hóa <span class="text-destructive">*</span></th>
                                    <th class="px-3 py-2.5 text-left font-medium">ĐVT <span class="text-destructive">*</span></th>
                                    <th class="px-3 py-2.5 text-right font-medium">SL</th>
                                    <th class="px-3 py-2.5 text-right font-medium">SL quy đổi</th>
                                    <th class="px-3 py-2.5 text-right font-medium">Giá nhập</th>
                                    <th class="px-3 py-2.5 text-right font-medium">VAT %</th>
                                    <th class="px-3 py-2.5 text-right font-medium">Thành tiền</th>
                                    <th class="px-3 py-2.5 text-center font-medium"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in form.items" :key="index" class="border-t">
                                    <td class="px-3 py-2.5 align-top">
                                        <div v-if="itemVariantMeta(item)" class="rounded-lg border border-border/70 bg-background px-3 py-2.5">
                                            <p class="truncate text-sm font-medium">{{ itemVariantMeta(item)?.productName }}</p>
                                            <p class="truncate text-[13px] font-bold text-muted-foreground">
                                                Tồn hiện tại: {{ stockPrimaryLabel(item) }}
                                                <span v-if="stockBreakdownLabel(item)">({{ stockBreakdownLabel(item) }})</span>
                                            </p>
                                            <p class="truncate text-xs text-muted-foreground">{{ itemVariantMeta(item)?.variantName }}</p>
                                            <p class="mt-1 text-[11px] text-muted-foreground">
                                                SKU: {{ itemVariantMeta(item)?.sku }} • Barcode: {{ itemVariantMeta(item)?.barcode }}
                                            </p>
                                        </div>
                                        <div v-else class="rounded-lg border border-destructive/40 bg-destructive/5 px-3 py-2.5 text-xs text-destructive">
                                            Hàng hóa không hợp lệ. Vui lòng xóa dòng và thêm lại.
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5 align-top">
                                        <div class="space-y-1.5">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger as-child>
                                                    <button type="button" class="inline-flex h-9 w-full items-center justify-between rounded-lg border border-border/70 bg-background px-2.5 py-1.5 text-left text-sm outline-none transition focus:ring-2 focus:ring-ring">
                                                        <span class="truncate">{{ itemUomLabel(item) }}</span>
                                                        <ChevronDown class="ml-2 size-4 shrink-0 text-muted-foreground" />
                                                    </button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-64">
                                                    <DropdownMenuLabel>Đơn vị tính</DropdownMenuLabel>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuRadioGroup
                                                        :model-value="item.selected_uom_key"
                                                        @update:model-value="(value) => onUomChange(item, value)"
                                                    >
                                                        <DropdownMenuRadioItem
                                                            v-for="option in itemUomOptions(item.product_variant_id)"
                                                            :key="option.key"
                                                            :value="option.key"
                                                        >
                                                            {{ option.label }}
                                                        </DropdownMenuRadioItem>
                                                    </DropdownMenuRadioGroup>
                                                </DropdownMenuContent>
                                            </DropdownMenu>

                                            <div v-if="itemUomOptions(item.product_variant_id).length === 0" class="text-[11px] text-muted-foreground">
                                                Chưa có ĐVT cho biến thể này.
                                                <button type="button" class="font-medium text-primary underline-offset-2 hover:underline" @click="openUomModal(item)">
                                                    Tạo UOM mới
                                                </button>
                                                rồi cấu hình trong sản phẩm.
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5 align-top">
                                        <input v-model.number="item.quantity" type="number" min="1" class="h-9 w-full rounded-lg border border-border/70 bg-background px-2.5 py-1.5 text-right text-sm outline-none transition focus:ring-2 focus:ring-ring" @input="onQuantityChange(item)" />
                                    </td>
                                    <td class="px-3 py-2.5 align-top">
                                        <input v-model.number="item.import_price" type="number" min="0" step="0.01" class="h-9 w-full rounded-lg border border-border/70 bg-background px-2.5 py-1.5 text-right text-sm outline-none transition focus:ring-2 focus:ring-ring" />
                                    </td>
                                    <td class="px-3 py-2.5 align-top">
                                        <input v-model.number="item.tax_rate" type="number" min="0" max="100" step="0.01" class="h-9 w-full rounded-lg border border-border/70 bg-background px-2.5 py-1.5 text-right text-sm outline-none transition focus:ring-2 focus:ring-ring" />
                                    </td>
                                    <td class="px-3 py-2.5 text-right align-top font-medium">{{ formatMoney(itemTotal(item)) }}</td>
                                    <td class="px-3 py-2.5 text-center align-top">
                                        <button type="button" class="rounded-md p-1 text-destructive hover:bg-destructive/10" @click="removeItem(index)">
                                            <Trash2 class="size-4" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="form.items.length === 0" class="border-t">
                                    <td colspan="8" class="px-3 py-8 text-center text-sm text-muted-foreground">
                                        Chưa có hàng hóa. Bấm <span class="font-medium">Thêm hàng hóa</span> để bắt đầu.
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="border-t bg-muted/20">
                                <tr>
                                    <td colspan="6" class="px-3 py-2.5 text-right font-semibold">Tổng cộng</td>
                                    <td class="px-3 py-2.5 text-right text-base font-semibold">{{ formatMoney(grandTotal) }}</td>
                                    <td class="px-3 py-2.5"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p v-if="form.errors.items" class="text-xs text-destructive">{{ form.errors.items }}</p>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-50">
                        <Save class="size-4" />
                        {{ isEdit ? 'Lưu cập nhật' : 'Tạo phiếu nhập' }}
                    </button>
                    <Link :href="tenantRoutes.importReceipts.index()" class="rounded-xl border border-border/70 bg-background px-4 py-2.5 text-sm transition hover:bg-muted/60">Hủy</Link>
                </div>
            </form>

            <UOMFormModal
                :open="uomModalOpen"
                mode="create"
                :uom="null"
                :uoms="localUoms"
                @update:open="(value) => (uomModalOpen = value)"
                @saved="handleUomSaved"
            />
            <div v-if="uomModalOpen && selectedProductEditHref" class="rounded-xl border border-border/70 bg-muted/20 px-4 py-3 text-sm text-muted-foreground">
                Sau khi tạo UOM, vào
                <Link :href="selectedProductEditHref" class="font-medium text-primary underline-offset-2 hover:underline">
                    cấu hình sản phẩm/biến thể
                </Link>
                để gán hệ số quy đổi cho biến thể trước khi chọn lại ĐVT tại phiếu nhập.
            </div>
        </div>
    </TenantLayout>
</template>
