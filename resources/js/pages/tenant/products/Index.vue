<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, Eye, FilePenLine, MoreHorizontal, Package, Plus, RotateCcw, Search, Trash2, List } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import ImportProductListModal from '@/components/tenant/products/modals/ImportProductListModal.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { VariantOption } from '@/composables/pos/models';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { productPublicRef } from '@/lib/productPublicRef';
import { tenantRoutes } from '@/lib/tenantRoutes';

// ── Types ───────────────────────────────────────────────────────────────────

type VariantRow = {
    id: number;
    name?: string;
    sku?: string | null;
    barcode?: string | null;
    base_unit?: string | null;
    price?: string | number | null;
    cost_price?: string | number | null;
    wholesale_price?: string | number | null;
    is_active?: boolean;
    is_default?: boolean;
    stocks?: Array<{
        id: number;
        quantity?: number | string | null;
    }>;
    units?: Array<{
        id: number;
        uom_id: number;
        conversion_factor?: number | string | null;
        uom?: {
            id: number;
            name: string;
            symbol?: string | null;
        } | null;
    }>;
};

type ProductRow = {
    id: number;
    name: string;
    description?: string | null;
    sku?: string | null;
    barcode?: string | null;
    is_active: boolean;
    base_unit?: string | null;
    brand?: string | null;
    origin?: string | null;
    category?: { id: number; name: string } | null;
    variants?: VariantRow[];
    images?: Array<{ id: number; image_path?: string | null; is_default?: boolean }>;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type ProductPagination = {
    data: ProductRow[];
    current_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
};

const props = defineProps<{
    products: ProductPagination;
    categories: Array<{ id: number; name: string }>;
    uoms: Array<{ id: number; name: string; symbol: string | null }>;
    warehouses: Array<{ id: number; name: string }>;
}>();

const showImportModal = ref(false);

// ── Filters ─────────────────────────────────────────────────────────────────

const filters = reactive({
    search: new URLSearchParams(window.location.search).get('search') ?? '',
    categoryId: new URLSearchParams(window.location.search).get('category_id') ?? '',
    isActive: new URLSearchParams(window.location.search).get('is_active') ?? '',
    perPage: String(props.products.per_page ?? 20),
});

// ── Computed ────────────────────────────────────────────────────────────────

const hasRows = computed(() => props.products.data.length > 0);
const activeCount = computed(() => props.products.data.filter((p) => p.is_active).length);
const inactiveCount = computed(() => props.products.data.filter((p) => !p.is_active).length);
const categoryFilterLabel = computed(() => {
    if (filters.categoryId === '') return 'Tất cả danh mục';
    const found = props.categories.find((category) => String(category.id) === filters.categoryId);
    return found?.name ?? 'Tất cả danh mục';
});
const statusFilterLabel = computed(() => {
    if (filters.isActive === 'true') return 'Đang bán';
    if (filters.isActive === 'false') return 'Ngừng bán';
    return 'Tất cả trạng thái';
});
const perPageLabel = computed(() => `${filters.perPage} / trang`);
const productVariantOptions = computed<VariantOption[]>(() => {
    const mapped: VariantOption[] = [];

    for (const product of props.products.data) {
        const variants = product.variants ?? [];
        if (variants.length === 0) continue;

        for (const variant of variants) {
            mapped.push({
                id: variant.id,
                product_id: product.id,
                product_name: product.name,
                name: variant.name ?? product.name,
                sku: variant.sku ?? product.sku ?? null,
                barcode: variant.barcode ?? product.barcode ?? null,
                base_unit: variant.base_unit ?? product.base_unit ?? null,
                price: Number(variant.price ?? 0) || 0,
                total_stock: Array.isArray(variant.stocks)
                    ? variant.stocks.reduce((sum, stock) => {
                        const quantity = typeof stock.quantity === 'string'
                            ? parseFloat(stock.quantity)
                            : Number(stock.quantity ?? 0);
                        return sum + (Number.isFinite(quantity) ? Math.max(0, quantity) : 0);
                    }, 0)
                    : 0,
                stock_by_warehouse: undefined,
                image_path: defaultImage(product),
            });
        }
    }

    return mapped;
});

// ── Helpers ─────────────────────────────────────────────────────────────────

const formatCurrency = (value: string | number | null | undefined): string => {
    if (value === null || value === undefined) return '-';
    const num = typeof value === 'string' ? parseFloat(value) : value;
    if (isNaN(num)) return '-';
    return new Intl.NumberFormat('vi-VN').format(num);
};

const defaultImage = (row: ProductRow) => {
    const image = row.images?.find((i) => i.is_default) ?? row.images?.[0];
    return image?.image_path || null;
};

const displayVariant = (row: ProductRow): VariantRow | null => {
    if (!row.variants || row.variants.length === 0) return null;
    return row.variants.find((variant) => variant.is_default) ?? row.variants[0] ?? null;
};

const extraVariantCount = (row: ProductRow): number => {
    if (!row.variants || row.variants.length === 0) return 0;

    const nonDefaultCount = row.variants.filter((variant) => variant.is_default !== true).length;
    if (nonDefaultCount > 0) return nonDefaultCount;

    return row.variants.length > 1 ? row.variants.length - 1 : 0;
};

const currentStock = (row: ProductRow): number => {
    const variant = displayVariant(row);
    if (!variant?.stocks?.length) return 0;

    return variant.stocks.reduce((sum, stock) => {
        const quantity = typeof stock.quantity === 'string'
            ? parseFloat(stock.quantity)
            : Number(stock.quantity ?? 0);
        return sum + (Number.isFinite(quantity) ? Math.max(0, quantity) : 0);
    }, 0);
};

type VariantUomStock = {
    label: string;
    factor: number;
};

const stockUnits = (variant: VariantRow): VariantUomStock[] => {
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

const highestStockUnit = (variant: VariantRow, baseStock: number): VariantUomStock | null => {
    const units = stockUnits(variant);
    if (units.length === 0) return null;

    return units.find((unit) => baseStock >= unit.factor) ?? units[units.length - 1];
};

const stockLabel = (row: ProductRow): string => {
    const variant = displayVariant(row);
    if (!variant) return '-';

    const quantity = currentStock(row);
    const chosenUnit = highestStockUnit(variant, quantity);
    if (!chosenUnit) {
        const formattedBase = new Intl.NumberFormat('vi-VN').format(quantity);
        const unit = variant.base_unit || row.base_unit || '';
        return unit ? `${formattedBase} ${unit}` : formattedBase;
    }

    const converted = Math.floor(quantity / chosenUnit.factor);
    const formatted = new Intl.NumberFormat('vi-VN').format(converted);
    return `${formatted} ${chosenUnit.label}`;
};

const stockBreakdown = (row: ProductRow): string => {
    const variant = displayVariant(row);
    if (!variant) return '';

    const units = stockUnits(variant);
    if (units.length === 0) return '';

    const baseQuantity = currentStock(row);
    const chosen = highestStockUnit(variant, baseQuantity);
    if (!chosen) return '';

    const baseUnit = units.find((unit) => unit.factor === 1);
    const chosenCount = Math.floor(baseQuantity / chosen.factor);
    let remaining = baseQuantity - (chosenCount * chosen.factor);

    if (remaining <= 0) {
        if (chosen.factor === 1 || !baseUnit) {
            return '';
        }

        return `= ${new Intl.NumberFormat('vi-VN').format(baseQuantity)} ${baseUnit.label}`;
    }

    const restUnits = units.filter((unit) => unit.factor < chosen.factor);
    const parts: string[] = [];

    for (const unit of restUnits) {
        if (unit.factor <= 0) continue;

        const count = Math.floor(remaining / unit.factor);
        if (count <= 0) continue;

        parts.push(`${new Intl.NumberFormat('vi-VN').format(count)} ${unit.label}`);
        remaining -= count * unit.factor;
    }

    if (parts.length === 0 && baseUnit) {
        return `+ ${new Intl.NumberFormat('vi-VN').format(remaining)} ${baseUnit.label}`;
    }

    if (parts.length === 0) {
        return '';
    }

    return `+ ${parts.join(' + ')}`;
};

// ── Actions ─────────────────────────────────────────────────────────────────

const filterPayload = () => {
    const query: Record<string, string> = { per_page: filters.perPage };
    if (filters.search.trim() !== '') query.search = filters.search.trim();
    if (filters.categoryId !== '') query.category_id = filters.categoryId;
    if (filters.isActive !== '') query.is_active = filters.isActive;
    return query;
};

const applyFilters = () => {
    router.get(tenantRoutes.products.index(), filterPayload(), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    filters.search = '';
    filters.categoryId = '';
    filters.isActive = '';
    filters.perPage = '20';
    router.get(tenantRoutes.products.index(), { per_page: '20' }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const navigateTo = (url: string | null) => {
    if (!url) return;
    router.visit(url, { preserveScroll: true, preserveState: true });
};

const deleteProduct = (row: ProductRow) => {
    if (!confirm('Bạn có chắc muốn xóa sản phẩm này?')) return;
    router.delete(tenantRoutes.products.destroy(productPublicRef(row)));
};
</script>

<template>
    <Head title="Quản lý hàng hóa" />
    <TenantLayout>
        <div class="space-y-6">
            <section class="rounded-2xl border border-border/70 bg-card/95 p-6 shadow-sm backdrop-blur-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="mt-2 text-2xl font-semibold tracking-tight">Quản lý hàng hóa</h1>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-border/70 bg-background px-4 py-2.5 text-sm font-medium transition hover:bg-muted/60"
                            @click="showImportModal = true"
                        >
                            <List class="size-4" />
                            Nhập danh sách
                        </button>
                        <Link
                            :href="tenantRoutes.products.create()"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                        >
                            <Plus class="size-4" />
                            Thêm hàng hóa
                        </Link>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-3">
                    <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                        <div class="mb-2 inline-flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Package class="size-4" />
                        </div>
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Tổng sản phẩm</p>
                        <p class="mt-1 text-2xl font-semibold">{{ props.products.total }}</p>
                    </div>
                    <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                        <div class="mb-2 inline-flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Package class="size-4" />
                        </div>
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Đang kinh doanh</p>
                        <p class="mt-1 text-2xl font-semibold">{{ activeCount }}</p>
                    </div>
                    <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                        <div class="mb-2 inline-flex size-9 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                            <Package class="size-4" />
                        </div>
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Tạm ngưng</p>
                        <p class="mt-1 text-2xl font-semibold">{{ inactiveCount }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-border/70 bg-card p-4">
                <div class="grid gap-3 md:grid-cols-5">
                    <div class="relative md:col-span-2">
                        <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="filters.search"
                            type="text"
                            placeholder="Tìm theo tên, SKU, barcode..."
                            class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 pl-9 text-sm outline-none transition focus:ring-2 focus:ring-ring"
                            @keydown.enter="applyFilters"
                        />
                    </div>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button type="button" class="inline-flex w-full items-center justify-between rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring">
                                <span class="truncate">{{ categoryFilterLabel }}</span>
                                <ChevronDown class="ml-2 size-4 text-muted-foreground" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent class="w-64">
                            <DropdownMenuLabel>Danh mục</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuRadioGroup v-model="filters.categoryId">
                                <DropdownMenuRadioItem value="">Tất cả danh mục</DropdownMenuRadioItem>
                                <DropdownMenuRadioItem
                                    v-for="c in props.categories"
                                    :key="c.id"
                                    :value="String(c.id)"
                                >
                                    {{ c.name }}
                                </DropdownMenuRadioItem>
                            </DropdownMenuRadioGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button type="button" class="inline-flex w-full items-center justify-between rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring">
                                <span class="truncate">{{ statusFilterLabel }}</span>
                                <ChevronDown class="ml-2 size-4 text-muted-foreground" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent class="w-56">
                            <DropdownMenuLabel>Trạng thái</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuRadioGroup v-model="filters.isActive">
                                <DropdownMenuRadioItem value="">Tất cả trạng thái</DropdownMenuRadioItem>
                                <DropdownMenuRadioItem value="true">Đang bán</DropdownMenuRadioItem>
                                <DropdownMenuRadioItem value="false">Ngừng bán</DropdownMenuRadioItem>
                            </DropdownMenuRadioGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button type="button" class="inline-flex w-full items-center justify-between rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring">
                                <span class="truncate">{{ perPageLabel }}</span>
                                <ChevronDown class="ml-2 size-4 text-muted-foreground" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent class="w-40">
                            <DropdownMenuLabel>Số dòng</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuRadioGroup v-model="filters.perPage">
                                <DropdownMenuRadioItem value="10">10 / trang</DropdownMenuRadioItem>
                                <DropdownMenuRadioItem value="20">20 / trang</DropdownMenuRadioItem>
                                <DropdownMenuRadioItem value="50">50 / trang</DropdownMenuRadioItem>
                                <DropdownMenuRadioItem value="100">100 / trang</DropdownMenuRadioItem>
                            </DropdownMenuRadioGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                        @click="applyFilters"
                    >
                        <Search class="size-3.5" />
                        Lọc dữ liệu
                    </button>
                    <button
                        class="inline-flex items-center gap-1.5 rounded-xl border border-border/70 bg-background px-4 py-2 text-sm transition hover:bg-muted/60"
                        @click="resetFilters"
                    >
                        <RotateCcw class="size-3.5" />
                        Đặt lại
                    </button>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-border/70 bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="whitespace-nowrap p-3 font-medium">Sản phẩm</th>
                                <th class="whitespace-nowrap p-3 font-medium">SKU</th>
                                <th class="whitespace-nowrap p-3 font-medium">Danh mục</th>
                                <th class="whitespace-nowrap p-3 font-medium">ĐVT</th>
                                <th class="whitespace-nowrap p-3 font-medium text-right">Tồn hiện tại</th>
                                <th class="whitespace-nowrap p-3 font-medium text-right">Giá vốn</th>
                                <th class="whitespace-nowrap p-3 font-medium text-right">Giá sỉ</th>
                                <th class="whitespace-nowrap p-3 font-medium text-right">Giá lẻ</th>
                                <th class="whitespace-nowrap p-3 text-center font-medium">Trạng thái</th>
                                <th class="whitespace-nowrap p-3 text-center font-medium">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!hasRows">
                                <td colspan="11" class="p-8 text-center text-muted-foreground">
                                    Không tìm thấy sản phẩm nào.
                                </td>
                            </tr>
                            <tr
                                v-for="row in props.products.data"
                                :key="row.id"
                                class="border-t transition-colors hover:bg-muted/20"
                            >

                                <!-- Product (image + name) -->
                                <td class="p-3">
                                    <div class="flex items-center gap-3">
                                        <div class="group/img relative size-10 shrink-0 overflow-hidden rounded-lg border border-border/70 bg-muted/40">
                                            <img
                                                v-if="defaultImage(row)"
                                                :src="defaultImage(row)!"
                                                :alt="row.name"
                                                class="size-full object-cover transition-transform duration-300 group-hover/img:scale-[3]"
                                            />
                                            <div v-else class="flex size-full items-center justify-center text-[10px] text-muted-foreground">N/A</div>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate font-medium">{{ row.name }}</p>
                                            <p class="truncate text-xs text-muted-foreground">{{ row.brand || row.barcode || '-' }}</p>
                                        </div>
                                    </div>
                                </td>

                                <!-- SKU -->
                                <td class="p-3 font-mono text-xs">{{ row.sku || '-' }}</td>

                                <!-- Category -->
                                <td class="p-3">{{ row.category?.name || '-' }}</td>

                                <!-- Unit -->
                                <td class="p-3">
                                    <span class="whitespace-nowrap">{{ row.base_unit || '-' }}</span>
                                    <span
                                        v-if="extraVariantCount(row) > 0"
                                        class="ml-1 rounded-full bg-primary/10 px-1.5 py-0.5 text-xs text-primary"
                                    >
                                        +{{ extraVariantCount(row) }} biến thể
                                    </span>
                                </td>

                                <!-- Current Stock -->
                                <td class="p-3 text-right font-medium tabular-nums">
                                    <p>{{ stockLabel(row) }}</p>
                                    <p v-if="stockBreakdown(row)" class="text-xs font-normal text-muted-foreground">{{ stockBreakdown(row) }}</p>
                                </td>

                                <!-- Cost Price -->
                                <td class="p-3 text-right tabular-nums">
                                    {{ formatCurrency(displayVariant(row)?.cost_price) }}
                                </td>

                                <!-- Wholesale Price -->
                                <td class="p-3 text-right tabular-nums">
                                    {{ formatCurrency(displayVariant(row)?.wholesale_price) }}
                                </td>

                                <!-- Retail Price -->
                                <td class="p-3 text-right font-medium tabular-nums">
                                    {{ formatCurrency(displayVariant(row)?.price) }}
                                </td>

                                <!-- Status -->
                                <td class="p-3 text-center">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="row.is_active
                                            ? 'bg-primary/15 text-primary'
                                            : 'bg-muted text-muted-foreground'"
                                    >
                                        {{ row.is_active ? 'Đang bán' : 'Ngừng' }}
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="p-3">
                                    <div class="flex items-center justify-center">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <button type="button" class="rounded-lg p-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground" title="Thao tác">
                                                    <MoreHorizontal class="size-4" />
                                                </button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-40">
                                                <DropdownMenuItem :as-child="true">
                                                    <Link :href="tenantRoutes.products.show(productPublicRef(row))" class="flex items-center gap-2">
                                                        <Eye class="size-4" />
                                                        Xem
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem :as-child="true">
                                                    <Link :href="tenantRoutes.products.edit(productPublicRef(row))" class="flex items-center gap-2">
                                                        <FilePenLine class="size-4" />
                                                        Sửa
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem variant="destructive" @click="deleteProduct(row)">
                                                    <Trash2 class="size-4" />
                                                    Xóa
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ── Pagination ────────────────────────────────────────────── -->
            <div v-if="hasRows" class="flex flex-col gap-3 rounded-2xl border border-border/70 bg-card px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                <div class="text-muted-foreground">
                    Hiển thị {{ props.products.from ?? 0 }} – {{ props.products.to ?? 0 }} / {{ props.products.total }}
                </div>
                <div class="flex items-center gap-1">
                    <button
                        v-for="link in props.products.links"
                        :key="link.label"
                        class="min-w-[32px] rounded-lg border border-border/70 px-2.5 py-1 text-center transition"
                        :class="link.active
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-background hover:bg-muted/50 disabled:opacity-40'"
                        :disabled="!link.url"
                        @click="navigateTo(link.url)"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </TenantLayout>

    <ImportProductListModal
        v-model="showImportModal"
        :uoms="props.uoms"
        :warehouses="props.warehouses"
        :variants="productVariantOptions"
        @success="router.reload({ only: ['products'] })"
    />
</template>
