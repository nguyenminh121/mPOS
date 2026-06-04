<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type BatchRow = {
    id: number;
    code: string;
    export_date?: string | null;
    mode: string;
    status: string;
    provider?: string | null;
    total_invoices?: number | string | null;
    total_amount?: number | string | null;
    exported_at?: string | null;
    creator?: { id: number; name: string } | null;
    details_count?: number;
};

type InvoiceCandidate = {
    id: number;
    code: string;
    status: string;
    e_invoice_status: string;
    e_invoiced_at?: string | null;
    sale_at?: string | null;
    total_amount?: number | string | null;
    order?: { id: number; code: string; status: string } | null;
    customer?: { id: number; name: string } | null;
};

type PaginationLink = { url: string | null; label: string; active: boolean };
type Pagination<T> = {
    data: T[];
    current_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
};

const props = defineProps<{
    batches: Pagination<BatchRow>;
    candidateInvoices: InvoiceCandidate[];
}>();

const form = useForm({
    invoice_refs: [] as string[],
    mode: 'BATCH',
    provider: 'MANUAL',
});

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
        NOT_EXPORTED: 'Chưa export',
    };

    return map[String(value ?? '').toUpperCase()] ?? String(value ?? '-');
};

const toggleInvoiceRef = (ref: string, checked: boolean) => {
    const normalizedRef = String(ref ?? '').trim();
    if (normalizedRef === '') return;

    const current = new Set(form.invoice_refs);
    if (checked) {
        current.add(normalizedRef);
    } else {
        current.delete(normalizedRef);
    }

    form.invoice_refs = Array.from(current);
};

const isChecked = (ref: string): boolean => form.invoice_refs.includes(String(ref ?? '').trim());

const submitExport = () => {
    if (form.invoice_refs.length === 0) {
        return;
    }

    form.post(tenantRoutes.eInvoices.store(), {
        preserveScroll: true,
    });
};

const updatePerPage = (value: string) => {
    const query = new URLSearchParams(window.location.search);
    query.set('per_page', value);
    query.delete('page');

    router.get(`${tenantRoutes.eInvoices.index()}?${query.toString()}`, {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="E-Invoices" />
    <TenantLayout>
        <div class="space-y-6 p-6">
            <section class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-amber-900">
                <p class="text-sm font-semibold">Chế độ Demo - Tính năng đang thử nghiệm</p>
                <p class="mt-1 text-sm">
                    Chức năng E-Invoices hiện đang trong giai đoạn testing. Vui lòng kiểm tra kỹ dữ liệu
                    và thao tác thận trọng trước khi sử dụng trong vận hành thực tế.
                </p>
            </section>

            <section class="rounded-2xl border border-border/70 bg-card p-5">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">E-Invoices - Danh sách phát hành</h1>
                        <p class="text-sm text-muted-foreground">Theo dõi batch phát hành và chi tiết hóa đơn điện tử.</p>
                    </div>
                    <div class="text-sm text-muted-foreground">
                        Tổng batch: <span class="font-medium text-foreground">{{ props.batches.total }}</span>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-border/70 bg-card p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-semibold">Tạo batch E-Invoices (manual)</h2>
                    <p class="text-xs text-muted-foreground">Chỉ nhận hóa đơn ISSUED và chưa export.</p>
                </div>

                <div class="overflow-x-auto rounded-xl border border-border/70">
                    <table class="w-full min-w-[960px] text-sm">
                        <thead class="bg-muted/40 text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium"></th>
                                <th class="px-3 py-2 text-left font-medium">Mã hóa đơn</th>
                                <th class="px-3 py-2 text-left font-medium">Đơn hàng</th>
                                <th class="px-3 py-2 text-left font-medium">Khách hàng</th>
                                <th class="px-3 py-2 text-right font-medium">Tổng tiền</th>
                                <th class="px-3 py-2 text-left font-medium">Ngày bán</th>
                                <th class="px-3 py-2 text-left font-medium">Trạng thái E-Invoices</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="invoice in props.candidateInvoices" :key="invoice.id" class="border-t border-border/70">
                                <td class="px-3 py-2">
                                    <input
                                        type="checkbox"
                                        :checked="isChecked(invoice.code)"
                                        @change="(event) => toggleInvoiceRef(invoice.code, (event.target as HTMLInputElement).checked)"
                                    >
                                </td>
                                <td class="px-3 py-2 font-medium">{{ invoice.code }}</td>
                                <td class="px-3 py-2">{{ invoice.order?.code || '-' }}</td>
                                <td class="px-3 py-2">{{ invoice.customer?.name || 'Khách lẻ' }}</td>
                                <td class="px-3 py-2 text-right">{{ formatCurrency(invoice.total_amount) }}</td>
                                <td class="px-3 py-2">{{ formatDateTime(invoice.sale_at) }}</td>
                                <td class="px-3 py-2">{{ statusLabel(invoice.e_invoice_status) }}</td>
                            </tr>
                            <tr v-if="props.candidateInvoices.length === 0" class="border-t border-border/70">
                                <td colspan="7" class="px-3 py-5 text-center text-muted-foreground">Không có hóa đơn đủ điều kiện phát hành E-Invoices.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
                        :disabled="form.processing || form.invoice_refs.length === 0"
                        @click="submitExport"
                    >
                        {{ form.processing ? 'Đang ghi nhận...' : `Phát hành ${form.invoice_refs.length} hóa đơn` }}
                    </button>
                    <p class="text-xs text-destructive" v-if="form.errors.invoice_refs">{{ form.errors.invoice_refs }}</p>
                </div>
            </section>

            <section class="rounded-2xl border border-border/70 bg-card p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-semibold">Lịch sử batch E-Invoices</h2>
                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                        <span>Số bản ghi/trang</span>
                        <select
                            class="rounded-md border border-border/70 bg-background px-2 py-1"
                            :value="String(props.batches.per_page || 20)"
                            @change="(event) => updatePerPage(String((event.target as HTMLSelectElement).value))"
                        >
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl border border-border/70">
                    <table class="w-full min-w-[980px] text-sm">
                        <thead class="bg-muted/40 text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">Mã batch</th>
                                <th class="px-3 py-2 text-left font-medium">Ngày phát hành</th>
                                <th class="px-3 py-2 text-left font-medium">Mode</th>
                                <th class="px-3 py-2 text-left font-medium">Provider</th>
                                <th class="px-3 py-2 text-right font-medium">Số hóa đơn</th>
                                <th class="px-3 py-2 text-right font-medium">Tổng tiền</th>
                                <th class="px-3 py-2 text-left font-medium">Trạng thái</th>
                                <th class="px-3 py-2 text-left font-medium">Người tạo</th>
                                <th class="px-3 py-2 text-right font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in props.batches.data" :key="row.id" class="border-t border-border/70">
                                <td class="px-3 py-2 font-medium">{{ row.code }}</td>
                                <td class="px-3 py-2">{{ formatDateTime(String(row.export_date || '')) }}</td>
                                <td class="px-3 py-2">{{ row.mode }}</td>
                                <td class="px-3 py-2">{{ row.provider || '-' }}</td>
                                <td class="px-3 py-2 text-right">{{ row.total_invoices || row.details_count || 0 }}</td>
                                <td class="px-3 py-2 text-right">{{ formatCurrency(row.total_amount) }}</td>
                                <td class="px-3 py-2">{{ statusLabel(row.status) }}</td>
                                <td class="px-3 py-2">{{ row.creator?.name || '-' }}</td>
                                <td class="px-3 py-2 text-right">
                                    <Link :href="tenantRoutes.eInvoices.show(row.id)" class="text-primary hover:underline">Chi tiết</Link>
                                </td>
                            </tr>
                            <tr v-if="props.batches.data.length === 0" class="border-t border-border/70">
                                <td colspan="9" class="px-3 py-5 text-center text-muted-foreground">Chưa có batch E-Invoices nào.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </TenantLayout>
</template>
