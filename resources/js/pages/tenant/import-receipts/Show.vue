<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Pencil } from 'lucide-vue-next';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type ReceiptDetail = {
    id: number;
    code: string;
    status: string;
    total_amount?: number | string | null;
    debt_amount?: number | string | null;
    import_date?: string | null;
    note?: string | null;
    supplier?: { id: number; name: string } | null;
    warehouse?: { id: number; name: string } | null;
    creator?: { id: number; name: string } | null;
    items?: Array<{
        id: number;
        unit_name?: string | null;
        quantity: number;
        quantity_in_base: number;
        import_price: number | string;
        tax_rate?: number | string | null;
        total?: number | string | null;
        product_variant?: {
            id: number;
            name: string;
            sku?: string | null;
            product?: { id: number; name: string } | null;
        } | null;
    }>;
};

const { receipt } = defineProps<{
    receipt: ReceiptDetail;
}>();

const formatMoney = (value: number | string | null | undefined) => {
    const amount = Number(value || 0);
    return new Intl.NumberFormat('vi-VN').format(amount);
};

const canEditReceipt = (status: string): boolean => {
    const normalized = String(status).toUpperCase();
    return normalized !== 'CANCELLED';
};

const statusLabelMap: Record<string, string> = {
    DRAFT: 'Nháp',
    PENDING: 'Chờ duyệt',
    APPROVED: 'Đã duyệt',
    COMPLETED: 'Hoàn tất',
    CANCELLED: 'Đã hủy',
};

const statusLabel = (status: string): string => {
    const normalized = String(status).toUpperCase();
    return statusLabelMap[normalized] ?? normalized;
};

const variantLabel = (item: NonNullable<ReceiptDetail['items']>[number]) => {
    const variant = item.product_variant;
    if (!variant) return '-';
    const productName = variant.product?.name ? `${variant.product.name} / ` : '';
    const sku = variant.sku ? ` (${variant.sku})` : '';
    return `${productName}${variant.name}${sku}`;
};
</script>

<template>
    <Head title="Chi tiết phiếu nhập" />
    <TenantLayout>
        <div class="mx-auto max-w-6xl space-y-6">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Link :href="tenantRoutes.importReceipts.index()" class="rounded-xl border border-border/70 bg-background p-2 transition hover:bg-muted/60">
                        <ArrowLeft class="size-5" />
                    </Link>
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">Chi tiết phiếu nhập</h1>
                        <p class="text-sm text-muted-foreground">{{ receipt.code }} · {{ statusLabel(receipt.status) }}</p>
                    </div>
                </div>

                <Link v-if="canEditReceipt(receipt.status)" :href="tenantRoutes.importReceipts.edit(receipt.id)" class="inline-flex items-center gap-2 rounded-xl border border-border/70 bg-background px-3 py-2 text-sm transition hover:bg-muted/60">
                    <Pencil class="size-4" />
                    Sửa
                </Link>
            </div>

            <section class="rounded-2xl border border-border/70 bg-card p-5">
                <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Kho</dt>
                        <dd class="mt-1 text-sm font-medium">{{ receipt.warehouse?.name || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Nhà cung cấp</dt>
                        <dd class="mt-1 text-sm font-medium">{{ receipt.supplier?.name || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Người tạo</dt>
                        <dd class="mt-1 text-sm font-medium">{{ receipt.creator?.name || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Ngày nhập</dt>
                        <dd class="mt-1 text-sm font-medium">{{ receipt.import_date || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Tổng tiền</dt>
                        <dd class="mt-1 text-base font-semibold">{{ formatMoney(receipt.total_amount) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Công nợ</dt>
                        <dd class="mt-1 text-base font-semibold">{{ formatMoney(receipt.debt_amount) }}</dd>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-2">
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Ghi chú</dt>
                        <dd class="mt-1 whitespace-pre-wrap text-sm text-muted-foreground">{{ receipt.note || '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="overflow-hidden rounded-2xl border border-border/70 bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[920px] text-sm">
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="p-3 font-medium">Biến thể</th>
                                <th class="p-3 font-medium">ĐVT</th>
                                <th class="p-3 text-right font-medium">SL</th>
                                <th class="p-3 text-right font-medium">SL quy đổi</th>
                                <th class="p-3 text-right font-medium">Giá nhập</th>
                                <th class="p-3 text-right font-medium">VAT %</th>
                                <th class="p-3 text-right font-medium">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!receipt.items || receipt.items.length === 0">
                                <td colspan="7" class="p-8 text-center text-muted-foreground">Không có dòng hàng.</td>
                            </tr>
                            <tr v-for="item in receipt.items || []" :key="item.id" class="border-t">
                                <td class="p-3">{{ variantLabel(item) }}</td>
                                <td class="p-3">{{ item.unit_name || '-' }}</td>
                                <td class="p-3 text-right">{{ item.quantity }}</td>
                                <td class="p-3 text-right">{{ item.quantity_in_base }}</td>
                                <td class="p-3 text-right">{{ formatMoney(item.import_price) }}</td>
                                <td class="p-3 text-right">{{ Number(item.tax_rate || 0) }}</td>
                                <td class="p-3 text-right font-semibold">{{ formatMoney(item.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </TenantLayout>
</template>
