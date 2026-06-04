<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    FilePenLine,
    ImageIcon,
    Trash2,
} from 'lucide-vue-next';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { productPublicRef } from '@/lib/productPublicRef';
import { tenantRoutes } from '@/lib/tenantRoutes';

// ── Types ───────────────────────────────────────────────────────────────────

type ProductDetail = {
    id: number;
    name: string;
    sku?: string | null;
    barcode?: string | null;
    description?: string | null;
    base_unit?: string | null;
    brand?: string | null;
    origin?: string | null;
    is_active: boolean;
    category?: { id: number; name: string } | null;
    tax_config?: { id: number; name: string } | null;
    suppliers?: Array<{ id: number; name: string }>;
    variants?: Array<{
        id: number;
        name: string;
        sku?: string | null;
        barcode?: string | null;
        base_unit?: string | null;
        price?: string | number | null;
        cost_price?: string | number | null;
        wholesale_price?: string | number | null;
        discount_price?: string | number | null;
        is_active?: boolean;
        units?: Array<{
            id: number;
            uom_id: number;
            conversion_factor?: string | number | null;
            uom?: { id: number; name: string; symbol?: string | null } | null;
        }>;
        prices?: Array<{
            id: number;
            uom_id?: number | null;
            cost_price?: string | number | null;
            wholesale_price?: string | number | null;
            retail_price?: string | number | null;
            currency?: string | null;
            uom?: { id: number; name: string; symbol?: string | null } | null;
        }>;
        stocks?: Array<{
            id: number;
            quantity?: string | number | null;
            warehouse_id?: number;
        }>;
        product_storage?: Array<{
            id: number;
            warehouse_location_id: number;
            min_stock_level?: string | number | null;
            max_stock_level?: string | number | null;
            warehouse_location?: {
                id: number;
                warehouse_id: number;
                parent_id?: number | null;
                code?: string | null;
                name: string;
                type: 'AREA' | 'FLOOR' | 'AISLE' | 'RACK' | 'SHELF' | 'BIN';
                warehouse?: { id: number; name: string } | null;
            } | null;
        }>;
    }>;
    images?: Array<{ id: number; image_path?: string | null; is_default?: boolean }>;
};

type WarehouseLocationOption = {
    id: number;
    warehouse_id: number;
    parent_id?: number | null;
    code?: string | null;
    name: string;
    type: 'AREA' | 'FLOOR' | 'AISLE' | 'RACK' | 'SHELF' | 'BIN';
    is_active: boolean;
    warehouse?: {
        id: number;
        name: string;
    } | null;
};

type ProductVariantDetail = NonNullable<ProductDetail['variants']>[number];
type ProductVariantUnitPrice = NonNullable<ProductVariantDetail['prices']>[number];

const props = defineProps<{
    product: ProductDetail;
    warehouseLocations: WarehouseLocationOption[];
}>();

// ── Helpers ─────────────────────────────────────────────────────────────────

const formatCurrency = (value: string | number | null | undefined): string => {
    if (value === null || value === undefined) return '-';
    const num = typeof value === 'string' ? parseFloat(value) : value;
    if (isNaN(num)) return '-';
    return new Intl.NumberFormat('vi-VN').format(num);
};

const primaryImage = () => {
    const img = props.product.images?.find((i) => i.is_default) ?? props.product.images?.[0];
    return img?.image_path || null;
};

const deleteProduct = () => {
    if (!confirm('Bạn có chắc muốn xóa sản phẩm này?')) return;
    router.delete(tenantRoutes.products.destroy(productPublicRef(props.product)));
};

const formatConversionFactor = (value: string | number | null | undefined): string => {
    if (value === null || value === undefined) return '-';
    const num = typeof value === 'string' ? parseFloat(value) : value;
    if (isNaN(num)) return '-';
    return Number.isInteger(num) ? `${num}` : num.toFixed(3).replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');
};

const getUnitPrice = (
    variant: ProductVariantDetail,
    uomId: number,
): ProductVariantUnitPrice | undefined => {
    return variant.prices?.find((price: ProductVariantUnitPrice) => Number(price.uom_id) === Number(uomId));
};

type VariantUomStock = {
    label: string;
    factor: number;
};

const totalBaseStock = (variant: ProductVariantDetail): number => {
    return (variant.stocks ?? []).reduce((sum, stock) => {
        const quantity = typeof stock.quantity === 'string' ? parseFloat(stock.quantity) : Number(stock.quantity ?? 0);
        return sum + (Number.isFinite(quantity) ? Math.max(0, quantity) : 0);
    }, 0);
};

const stockUnits = (variant: ProductVariantDetail): VariantUomStock[] => {
    const units: VariantUomStock[] = [];

    const baseLabel = String(variant.base_unit ?? '').trim();
    if (baseLabel !== '') {
        units.push({ label: baseLabel, factor: 1 });
    }

    for (const unit of variant.units ?? []) {
        const factor = Number(unit.conversion_factor ?? 1);
        if (!Number.isFinite(factor) || factor <= 0) continue;

        const name = String(unit.uom?.name ?? '').trim();
        if (name === '') continue;

        const symbol = String(unit.uom?.symbol ?? '').trim();
        const label = symbol !== '' ? `${name} (${symbol})` : name;
        units.push({ label, factor });
    }

    return units.sort((left, right) => right.factor - left.factor);
};

const highestStockUnit = (variant: ProductVariantDetail): VariantUomStock | null => {
    const units = stockUnits(variant);
    if (units.length === 0) return null;

    const baseStock = totalBaseStock(variant);
    return units.find((unit) => baseStock >= unit.factor) ?? units[units.length - 1];
};

const variantStockLabel = (variant: ProductVariantDetail): string => {
    const baseStock = totalBaseStock(variant);
    const chosen = highestStockUnit(variant);
    if (!chosen) {
        return new Intl.NumberFormat('vi-VN').format(baseStock);
    }

    const converted = Math.floor(baseStock / chosen.factor);
    return `${new Intl.NumberFormat('vi-VN').format(converted)} ${chosen.label}`;
};

const unitStockLabel = (variant: ProductVariantDetail, factorValue: string | number | null | undefined): string => {
    const factor = Number(factorValue ?? 1);
    if (!Number.isFinite(factor) || factor <= 0) return '-';

    const converted = Math.floor(totalBaseStock(variant) / factor);
    return new Intl.NumberFormat('vi-VN').format(converted);
};

const locationTypeLabelMap: Record<WarehouseLocationOption['type'], string> = {
    AREA: 'Khu vực',
    FLOOR: 'Tầng',
    AISLE: 'Lối',
    RACK: 'Kệ',
    SHELF: 'Ngăn',
    BIN: 'Ô',
};

const warehouseLocationsById = new Map<number, WarehouseLocationOption>(
    props.warehouseLocations.map((location) => [location.id, location]),
);

const warehouseLocationLabel = (warehouseLocationId: number | null | undefined): string => {
    if (typeof warehouseLocationId !== 'number') return '-';

    const location = warehouseLocationsById.get(warehouseLocationId);
    if (!location) return `#${warehouseLocationId}`;

    const breadcrumbs: string[] = [];
    const visited = new Set<number>();
    let current: WarehouseLocationOption | undefined = location;

    while (current) {
        if (visited.has(current.id)) break;
        visited.add(current.id);
        breadcrumbs.unshift(current.name);

        const parentId = current.parent_id ?? null;
        if (typeof parentId !== 'number') break;
        current = warehouseLocationsById.get(parentId);
    }

    const warehouseName = location.warehouse?.name ?? `Kho #${location.warehouse_id}`;
    const code = String(location.code ?? '').trim();
    const codeText = code !== '' ? ` (${code})` : '';
    const typeLabel = locationTypeLabelMap[location.type] ?? location.type;

    return `${warehouseName} · ${breadcrumbs.join(' / ')} · ${typeLabel}${codeText}`;
};
</script>

<template>
    <Head :title="`${props.product.name}`" />
    <TenantLayout>
        <div class="mx-auto max-w-screen-2xl space-y-5">
            <!-- ── Header ────────────────────────────────────────────── -->
            <div class="flex items-center justify-between rounded-2xl border border-border/70 bg-card/95 p-5 shadow-sm backdrop-blur-sm">
                <div class="flex items-center gap-3">
                    <Link
                        :href="tenantRoutes.products.index()"
                        class="rounded-xl border border-border/70 bg-background p-2 transition hover:bg-muted/60"
                    >
                        <ArrowLeft class="size-5" />
                    </Link>
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">{{ props.product.name }}</h1>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            SKU: {{ props.product.sku || '-' }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="tenantRoutes.products.edit(productPublicRef(props.product))"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                    >
                        <FilePenLine class="size-4" />
                        Sửa
                    </Link>
                    <button
                        class="inline-flex items-center gap-1.5 rounded-xl border border-destructive/30 bg-background px-4 py-2 text-sm font-medium text-destructive transition hover:bg-destructive/10"
                        @click="deleteProduct"
                    >
                        <Trash2 class="size-4" />
                        Xóa
                    </button>
                </div>
            </div>

            <!-- ── Inactive Warning ──────────────────────────────────── -->
            <div
                v-if="!props.product.is_active"
                class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
            >
                Sản phẩm này đang ở trạng thái <strong>ngừng bán</strong>. Sản phẩm sẽ không hiển thị trong POS và đơn hàng mới.
            </div>

            <!-- ── 3-Column Layout ───────────────────────────────────── -->
            <div class="grid gap-6 lg:grid-cols-[280px_1fr_300px]">
                <!-- ═══ LEFT: Images & Status ═════════════════════════ -->
                <div class="space-y-4">
                    <!-- Primary Image -->
                    <div class="rounded-2xl border border-border/70 bg-card p-4">
                        <div class="aspect-square overflow-hidden rounded-xl border border-border/70 bg-muted/40">
                            <img
                                v-if="primaryImage()"
                                :src="primaryImage()!"
                                :alt="props.product.name"
                                class="size-full object-cover"
                            />
                            <div v-else class="flex size-full flex-col items-center justify-center text-muted-foreground">
                                <ImageIcon class="mb-2 size-10" />
                                <p class="text-xs">Chưa có ảnh</p>
                            </div>
                        </div>

                        <!-- Thumbnail Gallery -->
                        <div v-if="props.product.images && props.product.images.length > 1" class="mt-3 grid grid-cols-4 gap-1.5">
                            <div
                                v-for="img in props.product.images"
                                :key="img.id"
                                class="relative aspect-square overflow-hidden rounded-lg border border-border/70 bg-muted/40"
                            >
                                <img
                                    v-if="img.image_path"
                                    :src="img.image_path"
                                    class="size-full object-cover"
                                />
                                <div
                                    v-if="img.is_default"
                                    class="absolute left-0.5 top-0.5 rounded bg-primary px-1 py-0.5 text-[9px] font-bold leading-none text-primary-foreground"
                                >
                                    Chính
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="rounded-2xl border border-border/70 bg-card p-5">
                        <h2 class="mb-3 text-sm font-semibold">Trạng thái</h2>
                        <span
                            class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                            :class="props.product.is_active
                                ? 'bg-primary/15 text-primary'
                                : 'bg-destructive/10 text-destructive'"
                        >
                            {{ props.product.is_active ? 'Đang bán' : 'Ngừng bán' }}
                        </span>

                        <h3 class="mb-2 mt-5 text-xs font-semibold text-muted-foreground uppercase tracking-wide">Nhà cung cấp</h3>
                        <ul v-if="props.product.suppliers?.length" class="space-y-1">
                            <li v-for="s in props.product.suppliers" :key="s.id" class="text-sm">{{ s.name }}</li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">Chưa liên kết.</p>
                    </div>
                </div>

                <!-- ═══ CENTER: Info ══════════════════════════════════ -->
                <div class="space-y-5">
                    <!-- Identity -->
                    <div class="rounded-2xl border border-border/70 bg-card p-5">
                        <h2 class="mb-4 text-sm font-semibold">Thông tin sản phẩm</h2>
                        <div class="grid gap-x-8 gap-y-3 text-sm md:grid-cols-2">
                            <div>
                                <span class="text-muted-foreground">Tên:</span>
                                <span class="ml-2 font-medium">{{ props.product.name }}</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground">SKU:</span>
                                <span class="ml-2 font-mono">{{ props.product.sku || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground">Barcode:</span>
                                <span class="ml-2 font-mono">{{ props.product.barcode || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground">Đơn vị tính:</span>
                                <span class="ml-2">{{ props.product.base_unit || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground">Thương hiệu:</span>
                                <span class="ml-2">{{ props.product.brand || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground">Xuất xứ:</span>
                                <span class="ml-2">{{ props.product.origin || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground">Danh mục:</span>
                                <span class="ml-2">{{ props.product.category?.name || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground">Cấu hình thuế:</span>
                                <span class="ml-2">{{ props.product.tax_config?.name || '-' }}</span>
                            </div>
                        </div>

                        <div v-if="props.product.description" class="mt-4 border-t pt-4">
                            <span class="text-sm text-muted-foreground">Mô tả:</span>
                            <p class="mt-1 text-sm">{{ props.product.description }}</p>
                        </div>
                    </div>

                    <!-- Variants -->
                    <div class="rounded-2xl border border-border/70 bg-card p-5">
                        <h2 class="mb-4 text-sm font-semibold">
                            Biến thể
                            <span class="ml-1 text-muted-foreground">({{ props.product.variants?.length ?? 0 }})</span>
                        </h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b bg-muted/30 text-left">
                                        <th class="whitespace-nowrap p-2.5 font-medium">Tên</th>
                                        <th class="whitespace-nowrap p-2.5 font-medium">SKU</th>
                                        <th class="whitespace-nowrap p-2.5 font-medium">ĐVT</th>
                                        <th class="whitespace-nowrap p-2.5 font-medium text-right">Tồn kho</th>
                                        <th class="whitespace-nowrap p-2.5 font-medium text-right">Giá vốn</th>
                                        <th class="whitespace-nowrap p-2.5 font-medium text-right">Giá sỉ</th>
                                        <th class="whitespace-nowrap p-2.5 font-medium text-right">Giá lẻ</th>
                                        <th class="whitespace-nowrap p-2.5 text-center font-medium">Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="v in props.product.variants || []" :key="v.id">
                                        <tr class="border-b">
                                            <td class="p-2.5 font-medium">{{ v.name }}</td>
                                            <td class="p-2.5 font-mono text-xs">{{ v.sku || '-' }}</td>
                                            <td class="p-2.5">{{ v.base_unit || '-' }}</td>
                                            <td class="p-2.5 text-right tabular-nums">{{ variantStockLabel(v) }}</td>
                                            <td class="p-2.5 text-right tabular-nums">{{ formatCurrency(v.cost_price) }}</td>
                                            <td class="p-2.5 text-right tabular-nums">{{ formatCurrency(v.wholesale_price) }}</td>
                                            <td class="p-2.5 text-right font-semibold tabular-nums">{{ formatCurrency(v.price) }}</td>
                                            <td class="p-2.5 text-center">
                                                <span
                                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                                    :class="v.is_active !== false
                                                        ? 'bg-primary/15 text-primary'
                                                        : 'bg-muted text-muted-foreground'"
                                                >
                                                    {{ v.is_active !== false ? 'Kích hoạt' : 'Tắt' }}
                                                </span>
                                            </td>
                                        </tr>

                                        <tr v-if="v.units?.length" class="border-b last:border-0 bg-muted/20">
                                            <td colspan="8" class="p-3">
                                                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                                    Quy đổi đơn vị & giá theo UOM
                                                </div>
                                                <div class="overflow-x-auto">
                                                    <table class="w-full text-xs">
                                                        <thead>
                                                            <tr class="border-b text-left text-muted-foreground">
                                                                <th class="whitespace-nowrap py-1.5 pr-3 font-medium">UOM</th>
                                                                <th class="whitespace-nowrap py-1.5 pr-3 font-medium">Tỷ lệ</th>
                                                                <th class="whitespace-nowrap py-1.5 pr-3 text-right font-medium">Tồn</th>
                                                                <th class="whitespace-nowrap py-1.5 pr-3 text-right font-medium">Giá vốn</th>
                                                                <th class="whitespace-nowrap py-1.5 pr-3 text-right font-medium">Giá sỉ</th>
                                                                <th class="whitespace-nowrap py-1.5 text-right font-medium">Giá lẻ</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr v-for="unit in v.units" :key="unit.id" class="border-b last:border-0">
                                                                <td class="py-1.5 pr-3 font-medium">
                                                                    {{ unit.uom?.name || '-' }}
                                                                    <span v-if="unit.uom?.symbol" class="text-muted-foreground">({{ unit.uom?.symbol }})</span>
                                                                </td>
                                                                <td class="py-1.5 pr-3">1 {{ unit.uom?.symbol || unit.uom?.name || '-' }} = {{ formatConversionFactor(unit.conversion_factor) }} {{ v.base_unit || '-' }}</td>
                                                                <td class="py-1.5 pr-3 text-right tabular-nums">{{ unitStockLabel(v, unit.conversion_factor) }}</td>
                                                                <td class="py-1.5 pr-3 text-right tabular-nums">{{ formatCurrency(getUnitPrice(v, unit.uom_id)?.cost_price) }}</td>
                                                                <td class="py-1.5 pr-3 text-right tabular-nums">{{ formatCurrency(getUnitPrice(v, unit.uom_id)?.wholesale_price) }}</td>
                                                                <td class="py-1.5 text-right font-semibold tabular-nums">{{ formatCurrency(getUnitPrice(v, unit.uom_id)?.retail_price) }}</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr v-if="v.product_storage?.length" class="border-b last:border-0 bg-muted/10">
                                            <td colspan="8" class="p-3">
                                                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                                    Vị trí cất trữ theo kho / tầng / kệ
                                                </div>
                                                <div class="overflow-x-auto">
                                                    <table class="w-full text-xs">
                                                        <thead>
                                                            <tr class="border-b text-left text-muted-foreground">
                                                                <th class="whitespace-nowrap py-1.5 pr-3 font-medium">Vị trí</th>
                                                                <th class="whitespace-nowrap py-1.5 pr-3 text-right font-medium">Tồn tối thiểu</th>
                                                                <th class="whitespace-nowrap py-1.5 text-right font-medium">Tồn tối đa</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr v-for="storage in v.product_storage" :key="storage.id" class="border-b last:border-0">
                                                                <td class="py-1.5 pr-3">{{ warehouseLocationLabel(storage.warehouse_location_id) }}</td>
                                                                <td class="py-1.5 pr-3 text-right tabular-nums">{{ formatCurrency(storage.min_stock_level) }}</td>
                                                                <td class="py-1.5 text-right tabular-nums">{{ formatCurrency(storage.max_stock_level) }}</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr v-if="!props.product.variants?.length">
                                        <td colspan="8" class="p-6 text-center text-muted-foreground">
                                            Chưa có biến thể nào.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ═══ RIGHT: Summary ═══════════════════════════════ -->
                <div class="space-y-4">
                    <!-- Quick pricing summary from first variant -->
                    <div v-if="props.product.variants?.length" class="rounded-2xl border border-border/70 bg-card p-5">
                        <h2 class="mb-4 text-sm font-semibold">Giá bán</h2>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted-foreground">Giá vốn</span>
                                <span class="text-sm font-medium tabular-nums">
                                    {{ formatCurrency(props.product.variants[0].cost_price) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted-foreground">Giá sỉ</span>
                                <span class="text-sm font-medium tabular-nums">
                                    {{ formatCurrency(props.product.variants[0].wholesale_price) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between border-t pt-3">
                                <span class="text-sm font-semibold">Giá lẻ</span>
                                <span class="text-lg font-bold tabular-nums text-primary">
                                    {{ formatCurrency(props.product.variants[0].price) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Product meta -->
                    <div class="rounded-2xl border border-border/70 bg-card p-5">
                        <h2 class="mb-3 text-sm font-semibold">Thông tin thêm</h2>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Số biến thể</span>
                                <span class="font-medium">{{ props.product.variants?.length ?? 0 }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Số ảnh</span>
                                <span class="font-medium">{{ props.product.images?.length ?? 0 }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">NCC liên kết</span>
                                <span class="font-medium">{{ props.product.suppliers?.length ?? 0 }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
