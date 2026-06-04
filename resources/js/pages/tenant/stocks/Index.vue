<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Eye } from 'lucide-vue-next';
import { computed } from 'vue';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type StockRow = {
    id: number;
    quantity: number;
    warehouse?: { id: number; name: string } | null;
    product_variant?: {
        id: number;
        name: string;
        sku?: string | null;
        product?: { id: number; name: string } | null;
    } | null;
};

const props = defineProps<{
    stocks: StockRow[];
}>();

const totalQuantity = computed(() => props.stocks.reduce((sum, item) => sum + Number(item.quantity || 0), 0));

const variantLabel = (row: StockRow) => {
    const variant = row.product_variant;
    if (!variant) return '-';
    const productName = variant.product?.name ? `${variant.product.name} / ` : '';
    const sku = variant.sku ? ` (${variant.sku})` : '';
    return `${productName}${variant.name}${sku}`;
};
</script>

<template>
    <Head title="Tồn kho" />
    <TenantLayout>
        <div class="space-y-6">
            <section class="rounded-2xl border border-border/70 bg-card/95 p-6 shadow-sm">
                <h1 class="text-2xl font-semibold tracking-tight">Tồn kho</h1>
                <p class="mt-1 text-sm text-muted-foreground">Theo dõi số lượng tồn theo kho và biến thể sản phẩm.</p>
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Bản ghi tồn kho</p>
                        <p class="mt-1 text-2xl font-semibold">{{ stocks.length }}</p>
                    </div>
                    <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Tổng số lượng</p>
                        <p class="mt-1 text-2xl font-semibold">{{ totalQuantity }}</p>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-border/70 bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="p-3 font-medium">Sản phẩm / Biến thể</th>
                                <th class="p-3 font-medium">Kho</th>
                                <th class="p-3 text-right font-medium">Số lượng</th>
                                <th class="p-3 text-center font-medium">Chi tiết</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="stocks.length === 0">
                                <td colspan="4" class="p-8 text-center text-muted-foreground">Chưa có dữ liệu tồn kho.</td>
                            </tr>
                            <tr v-for="row in stocks" :key="row.id" class="border-t hover:bg-muted/20">
                                <td class="p-3">{{ variantLabel(row) }}</td>
                                <td class="p-3">{{ row.warehouse?.name || '-' }}</td>
                                <td class="p-3 text-right font-semibold">{{ row.quantity }}</td>
                                <td class="p-3 text-center">
                                    <Link :href="tenantRoutes.stocks.show(row.id)" class="inline-flex items-center gap-1 rounded-md border border-border/70 px-2.5 py-1.5 text-xs hover:bg-muted/60">
                                        <Eye class="size-3.5" />
                                        Xem
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </TenantLayout>
</template>
