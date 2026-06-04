<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type BatchDetailRow = {
    id: number;
    invoice_code: string;
    order_code?: string | null;
    customer_name?: string | null;
    invoice_total?: number | string | null;
    invoice_sale_at?: string | null;
    exported_status: string;
    cqt_reference?: string | null;
    provider_reference?: string | null;
    error_message?: string | null;
    invoice?: { id: number; code: string; status: string; e_invoice_status: string; e_invoiced_at?: string | null } | null;
    order?: { id: number; code: string; status: string } | null;
};

type Batch = {
    id: number;
    code: string;
    export_date?: string | null;
    mode: string;
    status: string;
    provider?: string | null;
    cqt_reference?: string | null;
    total_invoices?: number | string | null;
    total_amount?: number | string | null;
    exported_at?: string | null;
    payload_snapshot?: string | null;
    response_snapshot?: string | null;
    note?: string | null;
    creator?: { id: number; name: string } | null;
    details: BatchDetailRow[];
};

const props = defineProps<{ batch: Batch }>();

const toNumber = (value: number | string | null | undefined): number => {
    if (value === null || value === undefined) return 0;
    const parsed = typeof value === 'string' ? parseFloat(value) : Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
};

const formatCurrency = (value: number | string | null | undefined) => new Intl.NumberFormat('vi-VN').format(toNumber(value));

const formatDateTime = (value: string | null | undefined): string => {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return '-';

    return new Intl.DateTimeFormat('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(parsed);
};

const statusLabel = (value: string): string => {
    const map: Record<string, string> = {
        DRAFT: 'Nháp',
        PROCESSING: 'Đang xử lý',
        EXPORTED: 'Đã export',
        FAILED: 'Thất bại',
        CANCELLED: 'Đã hủy',
        SKIPPED: 'Bỏ qua',
    };

    return map[String(value ?? '').toUpperCase()] ?? String(value ?? '-');
};
</script>

<template>
    <Head :title="`Chi tiết E-Invoices ${props.batch.code}`" />
    <TenantLayout>
        <div class="space-y-6 p-6">
            <section class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-amber-900">
                <p class="text-sm font-semibold">Chế độ Demo - Tính năng đang thử nghiệm</p>
                <p class="mt-1 text-sm">
                    Chức năng E-Invoices hiện đang trong giai đoạn testing. Vui lòng rà soát nội dung batch
                    cẩn thận và thao tác thận trọng trước khi áp dụng chính thức.
                </p>
            </section>

            <section class="rounded-2xl border border-border/70 bg-card p-5">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">Chi tiết batch E-Invoices {{ props.batch.code }}</h1>
                        <p class="text-sm text-muted-foreground">Theo dõi danh sách hóa đơn điện tử trong batch và trạng thái từng hóa đơn.</p>
                    </div>
                    <Link :href="tenantRoutes.eInvoices.index()" class="rounded-xl border border-border/70 bg-background px-4 py-2 text-sm hover:bg-muted">
                        Quay lại
                    </Link>
                </div>
            </section>

            <section class="grid gap-3 rounded-2xl border border-border/70 bg-card p-5 md:grid-cols-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Trạng thái</p>
                    <p class="mt-1 text-sm font-semibold">{{ statusLabel(props.batch.status) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Mode</p>
                    <p class="mt-1 text-sm font-semibold">{{ props.batch.mode }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Số hóa đơn</p>
                    <p class="mt-1 text-sm font-semibold">{{ props.batch.total_invoices || props.batch.details.length }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Tổng tiền</p>
                    <p class="mt-1 text-sm font-semibold">{{ formatCurrency(props.batch.total_amount) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Ngày phát hành</p>
                    <p class="mt-1 text-sm font-semibold">{{ formatDateTime(String(props.batch.export_date || '')) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Thời điểm phát hành</p>
                    <p class="mt-1 text-sm font-semibold">{{ formatDateTime(props.batch.exported_at) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Provider</p>
                    <p class="mt-1 text-sm font-semibold">{{ props.batch.provider || '-' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Người tạo</p>
                    <p class="mt-1 text-sm font-semibold">{{ props.batch.creator?.name || '-' }}</p>
                </div>
            </section>

            <section class="rounded-2xl border border-border/70 bg-card p-5">
                <h2 class="mb-3 text-base font-semibold">Danh sách hóa đơn điện tử trong batch</h2>
                <div class="overflow-x-auto rounded-xl border border-border/70">
                    <table class="w-full min-w-[1240px] text-sm">
                        <thead class="bg-muted/40 text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">Mã hóa đơn</th>
                                <th class="px-3 py-2 text-left font-medium">Mã đơn</th>
                                <th class="px-3 py-2 text-left font-medium">Khách hàng</th>
                                <th class="px-3 py-2 text-right font-medium">Tổng tiền</th>
                                <th class="px-3 py-2 text-left font-medium">Ngày bán</th>
                                <th class="px-3 py-2 text-left font-medium">KQ phát hành</th>
                                <th class="px-3 py-2 text-left font-medium">Provider Ref</th>
                                <th class="px-3 py-2 text-left font-medium">Lỗi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="detail in props.batch.details" :key="detail.id" class="border-t border-border/70">
                                <td class="px-3 py-2 font-medium">{{ detail.invoice_code }}</td>
                                <td class="px-3 py-2">{{ detail.order_code || '-' }}</td>
                                <td class="px-3 py-2">{{ detail.customer_name || '-' }}</td>
                                <td class="px-3 py-2 text-right">{{ formatCurrency(detail.invoice_total) }}</td>
                                <td class="px-3 py-2">{{ formatDateTime(detail.invoice_sale_at) }}</td>
                                <td class="px-3 py-2">{{ statusLabel(detail.exported_status) }}</td>
                                <td class="px-3 py-2">{{ detail.provider_reference || '-' }}</td>
                                <td class="px-3 py-2 text-destructive">{{ detail.error_message || '-' }}</td>
                            </tr>
                            <tr v-if="props.batch.details.length === 0" class="border-t border-border/70">
                                <td colspan="8" class="px-3 py-5 text-center text-muted-foreground">Batch chưa có hóa đơn chi tiết.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </TenantLayout>
</template>
