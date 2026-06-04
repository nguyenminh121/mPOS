<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, RotateCcw, CheckCircle2, Trash2 } from 'lucide-vue-next';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type VariantRow = {
    id: number;
    name: string;
    sku?: string | null;
    barcode?: string | null;
    base_unit?: string | null;
    product?: { id: number; name: string } | null;
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

type StockTakeDetail = {
    id: number;
    code: string;
    status: string;
    note?: string | null;
    completed_at?: string | null;
    warehouse?: { id: number; name: string } | null;
    creator?: { id: number; name: string } | null;
    items?: Array<{
        id: number;
        product_variant_id: number;
        system_qty: number;
        actual_qty: number;
        diff_qty: number;
        reason?: string | null;
        product_variant?: VariantRow | null;
    }>;
};

const props = defineProps<{
    stockTake: StockTakeDetail;
    permissions: {
        canUpdate: boolean;
        canComplete: boolean;
        canUndo: boolean;
        canDelete: boolean;
    };
}>();

const normalizeStatus = (status: string): 'DRAFT' | 'IN_PROGRESS' | 'COMPLETED' | 'CANCELLED' => {
    const normalized = String(status).toUpperCase();

    if (normalized === 'IN_PROGRESS') return 'IN_PROGRESS';
    if (normalized === 'COMPLETED') return 'COMPLETED';
    if (normalized === 'CANCELLED') return 'CANCELLED';

    return 'DRAFT';
};

const statusLabel = (status: string): string => {
    const normalized = normalizeStatus(status);

    if (normalized === 'IN_PROGRESS') return 'Đang kiểm kê';
    if (normalized === 'COMPLETED') return 'Hoàn tất';
    if (normalized === 'CANCELLED') return 'Đã hủy';

    return 'Nháp';
};

const statusBadgeClass = (status: string): string => {
    const normalized = normalizeStatus(status);

    if (normalized === 'IN_PROGRESS') return 'bg-amber-500/15 text-amber-700 dark:text-amber-300';
    if (normalized === 'COMPLETED') return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300';
    if (normalized === 'CANCELLED') return 'bg-destructive/15 text-destructive';

    return 'bg-muted text-muted-foreground';
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

const stockBreakdown = (variant: VariantRow, quantity: number): string => {
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
};

const primaryCountLabel = (variant: VariantRow, quantity: number): string => {
    const units = conversionUnitsForVariant(variant);
    if (units.length === 0) return String(quantity);

    const exact = units.find((unit) => unit.factor > 1 && Number.isInteger(quantity / unit.factor));
    const selected = exact ?? units.find((unit) => unit.factor === 1) ?? units[units.length - 1];

    const amount = quantity / selected.factor;

    return `${new Intl.NumberFormat('vi-VN', {
        minimumFractionDigits: Number.isInteger(amount) ? 0 : 2,
        maximumFractionDigits: 2,
    }).format(amount)} ${selected.name}`;
};

const breakdownLabel = (variant: VariantRow, quantity: number): string => {
    const breakdown = stockBreakdown(variant, quantity);
    if (breakdown === '') return '';

    const primary = primaryCountLabel(variant, quantity);
    return breakdown === primary ? '' : breakdown;
};

const variantLabel = (variant: VariantRow | null | undefined): string => {
    if (!variant) return '-';
    const productName = variant.product?.name ? `${variant.product.name} / ` : '';
    const sku = variant.sku ? ` (${variant.sku})` : '';
    return `${productName}${variant.name}${sku}`;
};

const completeStockTake = () => {
    if (!confirm('Xác nhận hoàn tất kiểm kê và cập nhật tồn kho?')) return;
    router.post(tenantRoutes.stockTakes.complete(props.stockTake.id));
};

const undoStockTake = () => {
    if (!confirm('Xác nhận undo để hoàn tác điều chỉnh tồn kho?')) return;
    router.post(tenantRoutes.stockTakes.undo(props.stockTake.id));
};

const deleteStockTake = () => {
    if (!confirm('Bạn có chắc muốn xóa phiếu kiểm kê này?')) return;
    router.delete(tenantRoutes.stockTakes.destroy(props.stockTake.id));
};
</script>

<template>
    <Head title="Chi tiết phiếu kiểm kê" />
    <TenantLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Link :href="tenantRoutes.stockTakes.index()" class="rounded-xl border border-border/70 bg-background p-2 transition hover:bg-muted/60">
                        <ArrowLeft class="size-5" />
                    </Link>
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">Chi tiết phiếu kiểm kê</h1>
                        <p class="text-sm text-muted-foreground">{{ stockTake.code }} · {{ statusLabel(stockTake.status) }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Link v-if="permissions.canUpdate" :href="tenantRoutes.stockTakes.edit(stockTake.id)" class="inline-flex items-center gap-2 rounded-xl border border-border/70 bg-background px-3 py-2 text-sm transition hover:bg-muted/60">
                        <Pencil class="size-4" />
                        Sửa
                    </Link>
                    <button v-if="permissions.canComplete" type="button" class="inline-flex items-center gap-2 rounded-xl bg-primary px-3 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90" @click="completeStockTake">
                        <CheckCircle2 class="size-4" />
                        Hoàn tất kiểm kê
                    </button>
                    <button v-if="permissions.canUndo" type="button" class="inline-flex items-center gap-2 rounded-xl border border-border/70 bg-background px-3 py-2 text-sm transition hover:bg-muted/60" @click="undoStockTake">
                        <RotateCcw class="size-4" />
                        Hoàn tác kiểm kê
                    </button>
                    <button v-if="permissions.canDelete" type="button" class="inline-flex items-center gap-2 rounded-xl border border-destructive/40 bg-background px-3 py-2 text-sm text-destructive transition hover:bg-destructive/10" @click="deleteStockTake">
                        <Trash2 class="size-4" />
                        Xóa
                    </button>
                </div>
            </div>

            <section class="rounded-2xl border border-border/70 bg-card p-5">
                <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Kho</dt>
                        <dd class="mt-1 text-sm font-medium">{{ stockTake.warehouse?.name || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Người tạo</dt>
                        <dd class="mt-1 text-sm font-medium">{{ stockTake.creator?.name || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Trạng thái</dt>
                        <dd class="mt-1 text-sm">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadgeClass(stockTake.status)">
                                {{ statusLabel(stockTake.status) }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Hoàn tất lúc</dt>
                        <dd class="mt-1 text-sm font-medium">{{ stockTake.completed_at || '-' }}</dd>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-4">
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Ghi chú</dt>
                        <dd class="mt-1 whitespace-pre-wrap text-sm text-muted-foreground">{{ stockTake.note || '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="overflow-hidden rounded-2xl border border-border/70 bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1100px] text-sm">
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="p-3 font-medium">Biến thể</th>
                                <th class="p-3 text-right font-medium">Tồn hệ thống</th>
                                <th class="p-3 text-right font-medium">Thực tế</th>
                                <th class="p-3 text-right font-medium">Chênh lệch</th>
                                <th class="p-3 font-medium">Lý do</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!(stockTake.items || []).length">
                                <td colspan="5" class="p-8 text-center text-muted-foreground">Không có dòng kiểm kê.</td>
                            </tr>
                            <tr v-for="item in stockTake.items || []" :key="item.id" class="border-t">
                                <td class="p-3">{{ variantLabel(item.product_variant) }}</td>
                                <td class="p-3 text-right">
                                    <p class="font-semibold">{{ item.system_qty }}</p>
                                    <p class="text-xs text-muted-foreground" v-if="item.product_variant">{{ primaryCountLabel(item.product_variant, item.system_qty) }}</p>
                                    <p class="text-xs text-muted-foreground" v-if="item.product_variant && breakdownLabel(item.product_variant, item.system_qty)">{{ breakdownLabel(item.product_variant, item.system_qty) }}</p>
                                </td>
                                <td class="p-3 text-right">
                                    <p class="font-semibold">{{ item.actual_qty }}</p>
                                    <p class="text-xs text-muted-foreground" v-if="item.product_variant">{{ primaryCountLabel(item.product_variant, item.actual_qty) }}</p>
                                    <p class="text-xs text-muted-foreground" v-if="item.product_variant && breakdownLabel(item.product_variant, item.actual_qty)">{{ breakdownLabel(item.product_variant, item.actual_qty) }}</p>
                                </td>
                                <td class="p-3 text-right">
                                    <span :class="Number(item.diff_qty) === 0 ? 'text-muted-foreground' : Number(item.diff_qty) > 0 ? 'text-primary' : 'text-destructive'" class="font-semibold">
                                        {{ Number(item.diff_qty) > 0 ? '+' : '' }}{{ item.diff_qty }}
                                    </span>
                                </td>
                                <td class="p-3 text-muted-foreground">{{ item.reason || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </TenantLayout>
</template>
