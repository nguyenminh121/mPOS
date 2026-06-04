<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type StockBatch = {
    id: number;
    batch_code?: string | null;
    cost_price?: number | string | null;
    initial_quantity?: number | null;
    remaining_quantity?: number | null;
    received_at?: string | null;
    expires_at?: string | null;
};

type StockMovement = {
    id: number;
    movement_type: string;
    quantity: number;
    balance_after?: number | null;
    cost_price_at_move?: number | string | null;
    note?: string | null;
    created_at?: string | null;
};

type StockDetail = {
    id: number;
    quantity: number;
    warehouse?: { id: number; name: string } | null;
    product_variant?: {
        id: number;
        name: string;
        sku?: string | null;
        product?: { id: number; name: string } | null;
        stock_batches?: StockBatch[];
        stock_movements?: StockMovement[];
    } | null;
};

const { stock } = defineProps<{
    stock: StockDetail;
}>();

const formatMoney = (value: number | string | null | undefined) => {
    const amount = Number(value || 0);
    return new Intl.NumberFormat('vi-VN').format(amount);
};

const variantLabel = () => {
    const variant = stock.product_variant;
    if (!variant) return '-';
    const productName = variant.product?.name ? `${variant.product.name} / ` : '';
    const sku = variant.sku ? ` (${variant.sku})` : '';
    return `${productName}${variant.name}${sku}`;
};
</script>

<template>
    <Head title="Chi tiết tồn kho" />
    <TenantLayout>
        <div class="mx-auto max-w-6xl space-y-6">
            <div class="flex items-center gap-3">
                <Link :href="tenantRoutes.stocks.index()" class="rounded-xl border border-border/70 bg-background p-2 transition hover:bg-muted/60">
                    <ArrowLeft class="size-5" />
                </Link>
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Chi tiết tồn kho</h1>
                    <p class="text-sm text-muted-foreground">{{ variantLabel() }}</p>
                </div>
            </div>

            <section class="rounded-2xl border border-border/70 bg-card p-5">
                <dl class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Kho</dt>
                        <dd class="mt-1 text-sm font-medium">{{ stock.warehouse?.name || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Biến thể</dt>
                        <dd class="mt-1 text-sm font-medium">{{ variantLabel() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Số lượng hiện có</dt>
                        <dd class="mt-1 text-base font-semibold">{{ stock.quantity }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-border/70 bg-card">
                <div class="border-b border-border/70 px-4 py-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Lô hàng</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="p-3 font-medium">Mã lô</th>
                                <th class="p-3 text-right font-medium">Giá vốn</th>
                                <th class="p-3 text-right font-medium">SL đầu</th>
                                <th class="p-3 text-right font-medium">SL còn</th>
                                <th class="p-3 font-medium">Ngày nhận</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!(stock.product_variant?.stock_batches || []).length">
                                <td colspan="5" class="p-6 text-center text-muted-foreground">Chưa có dữ liệu lô.</td>
                            </tr>
                            <tr v-for="batch in stock.product_variant?.stock_batches || []" :key="batch.id" class="border-t">
                                <td class="p-3 font-medium">{{ batch.batch_code || `BATCH-${batch.id}` }}</td>
                                <td class="p-3 text-right">{{ formatMoney(batch.cost_price) }}</td>
                                <td class="p-3 text-right">{{ batch.initial_quantity ?? 0 }}</td>
                                <td class="p-3 text-right font-semibold">{{ batch.remaining_quantity ?? 0 }}</td>
                                <td class="p-3">{{ batch.received_at || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-border/70 bg-card">
                <div class="border-b border-border/70 px-4 py-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Biến động gần đây</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-sm">
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="p-3 font-medium">Thời gian</th>
                                <th class="p-3 font-medium">Loại</th>
                                <th class="p-3 text-right font-medium">Số lượng</th>
                                <th class="p-3 text-right font-medium">Tồn sau</th>
                                <th class="p-3 text-right font-medium">Giá vốn tại thời điểm</th>
                                <th class="p-3 font-medium">Ghi chú</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!(stock.product_variant?.stock_movements || []).length">
                                <td colspan="6" class="p-6 text-center text-muted-foreground">Chưa có biến động kho.</td>
                            </tr>
                            <tr v-for="movement in stock.product_variant?.stock_movements || []" :key="movement.id" class="border-t">
                                <td class="p-3">{{ movement.created_at || '-' }}</td>
                                <td class="p-3 font-medium">{{ movement.movement_type }}</td>
                                <td class="p-3 text-right">{{ movement.quantity }}</td>
                                <td class="p-3 text-right">{{ movement.balance_after ?? '-' }}</td>
                                <td class="p-3 text-right">{{ formatMoney(movement.cost_price_at_move) }}</td>
                                <td class="p-3 text-muted-foreground">{{ movement.note || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </TenantLayout>
</template>
