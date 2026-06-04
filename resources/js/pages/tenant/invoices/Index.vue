<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileText, MoreHorizontal, SlidersHorizontal } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DateTimePicker from '@/components/DateTimePicker.vue';
import InvoiceDetailTab from '@/components/tenant/invoices/InvoiceDetailTab.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ExpandableRow, ExpandableRowToggle } from '@/components/ui/expandable-row';
import { FilterResetButton, FilterSearchInput, FilterTagList } from '@/components/ui/filter';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { invoicePublicRef } from '@/lib/invoicePublicRef';
import { formatDateTimeVnWithZone, parseUtc7DateTimeToMs } from '@/lib/invoiceTemplate';
import { tenantRoutes } from '@/lib/tenantRoutes';

type InvoiceItem = {
    id: number;
    product_name?: string | null;
    unit_name?: string | null;
    quantity?: number | string | null;
    price?: number | string | null;
    discount?: number | string | null;
    tax_rate?: number | string | null;
    total?: number | string | null;
};

type InvoiceRow = {
    id: number;
    code: string;
    status: string;
    sale_at?: string | null;
    subtotal?: number | string | null;
    tax_amount?: number | string | null;
    total_amount?: number | string | null;
    customer?: {
        id?: number;
        name?: string | null;
        tax_code?: string | null;
    } | null;
    order?: {
        id?: number;
        code?: string | null;
    } | null;
    items?: InvoiceItem[];
    created_at?: string | null;
    e_invoice_status?: string | null;
    e_invoiced_at?: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type InvoicePagination = {
    data: InvoiceRow[];
    current_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
};

const props = defineProps<{
    invoices: InvoicePagination;
}>();

const filters = ref({
    invoiceCode: '',
    orderCode: '',
    productKeyword: '',
    customerKeyword: '',
    customerId: '',
    taxCodeKeyword: '',
    status: '',
    fromAt: '',
    toAt: '',
    minTotal: '',
    maxTotal: '',
});

const expandedInvoiceId = ref<number | null>(null);

const toNumber = (value: number | string | null | undefined): number => {
    if (value === null || value === undefined) return 0;
    const parsed = typeof value === 'string' ? parseFloat(value) : Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
};

const formatCurrency = (value: number | string | null | undefined): string => {
    return new Intl.NumberFormat('vi-VN').format(toNumber(value));
};

const formatDateTime = (value: string | null | undefined): string => {
    return formatDateTimeVnWithZone(value ?? null);
};

const normalizeText = (value: string | null | undefined): string => {
    return String(value ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/[^a-z0-9\s]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
};

const toTokens = (value: string): string[] => normalizeText(value).split(' ').filter((token) => token.length > 0);

const getInvoiceStatusText = (status: string): string => {
    const map: Record<string, string> = {
        ISSUED: 'Đã phát hành',
        REPLACED: 'Đã thay thế',
        CANCELLED: 'Đã hủy',
    };

    return map[status] ?? status;
};

const statusBadgeClass = (status: string): string => {
    const classes: Record<string, string> = {
        ISSUED: 'bg-emerald-100 text-emerald-700',
        REPLACED: 'bg-indigo-100 text-indigo-700',
        CANCELLED: 'bg-red-100 text-red-700',
    };

    return classes[status] ?? 'bg-slate-100 text-slate-700';
};

const statusOptions = [
    { value: '', label: 'Tất cả trạng thái' },
    { value: 'ISSUED', label: 'Đã phát hành' },
    { value: 'REPLACED', label: 'Đã thay thế' },
    { value: 'CANCELLED', label: 'Đã hủy' },
];

const invoiceRows = computed(() => props.invoices?.data ?? []);

const customers = computed(() => {
    const rows = new Map<number, { id: number; name: string }>();

    invoiceRows.value.forEach((invoice) => {
        const id = invoice.customer?.id;
        const name = invoice.customer?.name;
        if (!id || !name) return;
        rows.set(id, { id, name });
    });

    const customerKeyword = normalizeText(filters.value.customerKeyword);
    const list = Array.from(rows.values()).sort((a, b) => a.name.localeCompare(b.name, 'vi'));

    if (customerKeyword === '') return list;

    return list.filter((customer) => normalizeText(customer.name).includes(customerKeyword));
});

const perPageOptions = [10, 20, 50, 100];

const selectedPerPage = computed(() => String(props.invoices.per_page || 20));

const getCurrentQuery = (): Record<string, string> => {
    const params = new URLSearchParams(window.location.search);
    const query: Record<string, string> = {};
    params.forEach((value, key) => {
        query[key] = value;
    });
    return query;
};

const updatePerPage = (value: string) => {
    const query: Record<string, string> = {
        ...getCurrentQuery(),
        per_page: value,
    };
    delete query.page;

    router.get(tenantRoutes.invoices.index(), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const matchesProductKeyword = (invoice: InvoiceRow, tokens: string[]) => {
    if (tokens.length === 0) return true;

    return (invoice.items ?? []).some((item) => {
        const searchable = normalizeText([
            item.product_name,
            item.unit_name,
        ].join(' '));

        return tokens.every((token) => searchable.includes(token));
    });
};

const inTimeRange = (invoice: InvoiceRow) => {
    const saleTime = parseUtc7DateTimeToMs(invoice.sale_at || invoice.created_at || null);
    if (saleTime === null) return false;

    if (filters.value.fromAt) {
        const fromTime = parseUtc7DateTimeToMs(filters.value.fromAt);
        if (fromTime !== null && saleTime < fromTime) {
            return false;
        }
    }

    if (filters.value.toAt) {
        const toTime = parseUtc7DateTimeToMs(filters.value.toAt);
        if (toTime !== null && saleTime > toTime) {
            return false;
        }
    }

    return true;
};

const filteredInvoices = computed(() => {
    const invoiceCodeKeyword = normalizeText(filters.value.invoiceCode);
    const orderCodeKeyword = normalizeText(filters.value.orderCode);
    const customerKeyword = normalizeText(filters.value.customerKeyword);
    const taxCodeKeyword = normalizeText(filters.value.taxCodeKeyword);
    const productTokens = toTokens(filters.value.productKeyword);

    return invoiceRows.value.filter((invoice) => {
        const invoiceCode = normalizeText(invoice.code);
        const orderCode = normalizeText(invoice.order?.code ?? '');
        const taxCode = normalizeText(invoice.customer?.tax_code ?? '');
        const totalAmount = toNumber(invoice.total_amount);
        const minTotal = filters.value.minTotal === '' ? null : toNumber(filters.value.minTotal);
        const maxTotal = filters.value.maxTotal === '' ? null : toNumber(filters.value.maxTotal);

        const matchInvoiceCode = invoiceCodeKeyword === '' || invoiceCode.includes(invoiceCodeKeyword);
        const matchOrderCode = orderCodeKeyword === '' || orderCode.includes(orderCodeKeyword);
        const matchCustomerKeyword = customerKeyword === '' || normalizeText(invoice.customer?.name ?? '').includes(customerKeyword);
        const matchTaxCode = taxCodeKeyword === '' || taxCode.includes(taxCodeKeyword);
        const matchProduct = matchesProductKeyword(invoice, productTokens);
        const matchStatus = filters.value.status === '' || invoice.status === filters.value.status;
        const matchCustomer = filters.value.customerId === '' || String(invoice.customer?.id ?? '') === filters.value.customerId;
        const matchMinTotal = minTotal === null || totalAmount >= minTotal;
        const matchMaxTotal = maxTotal === null || totalAmount <= maxTotal;
        const matchTime = inTimeRange(invoice);

        return matchInvoiceCode
            && matchOrderCode
            && matchCustomerKeyword
            && matchTaxCode
            && matchProduct
            && matchStatus
            && matchCustomer
            && matchMinTotal
            && matchMaxTotal
            && matchTime;
    });
});

const filteredSummary = computed(() => {
    return filteredInvoices.value.reduce((acc, invoice) => {
        acc.totalAmount += toNumber(invoice.total_amount);
        if (invoice.status === 'ISSUED') acc.issued += 1;
        if (invoice.status === 'REPLACED') acc.replaced += 1;
        if (invoice.status === 'CANCELLED') acc.cancelled += 1;
        return acc;
    }, {
        totalAmount: 0,
        issued: 0,
        replaced: 0,
        cancelled: 0,
    });
});

const activeFilterTags = computed(() => {
    const tags: Array<{ key: string; label: string; value: string }> = [];

    if (filters.value.invoiceCode.trim() !== '') {
        tags.push({ key: 'invoiceCode', label: 'Mã hóa đơn', value: filters.value.invoiceCode.trim() });
    }

    if (filters.value.orderCode.trim() !== '') {
        tags.push({ key: 'orderCode', label: 'Mã đơn hàng', value: filters.value.orderCode.trim() });
    }

    if (filters.value.productKeyword.trim() !== '') {
        tags.push({ key: 'productKeyword', label: 'Sản phẩm/ĐVT', value: filters.value.productKeyword.trim() });
    }

    if (filters.value.customerId !== '') {
        const name = customers.value.find((row) => String(row.id) === filters.value.customerId)?.name ?? filters.value.customerId;
        tags.push({ key: 'customerId', label: 'Khách hàng', value: name });
    }

    if (filters.value.taxCodeKeyword.trim() !== '') {
        tags.push({ key: 'taxCodeKeyword', label: 'MST', value: filters.value.taxCodeKeyword.trim() });
    }

    if (filters.value.status !== '') {
        tags.push({ key: 'status', label: 'Trạng thái', value: getInvoiceStatusText(filters.value.status) });
    }

    if (filters.value.fromAt !== '') {
        tags.push({ key: 'fromAt', label: 'Từ thời gian', value: formatDateTime(filters.value.fromAt) });
    }

    if (filters.value.toAt !== '') {
        tags.push({ key: 'toAt', label: 'Đến thời gian', value: formatDateTime(filters.value.toAt) });
    }

    if (filters.value.minTotal.trim() !== '') {
        tags.push({ key: 'minTotal', label: 'Tổng tiền tối thiểu', value: formatCurrency(filters.value.minTotal) });
    }

    if (filters.value.maxTotal.trim() !== '') {
        tags.push({ key: 'maxTotal', label: 'Tổng tiền tối đa', value: formatCurrency(filters.value.maxTotal) });
    }

    return tags;
});

const resetFilters = () => {
    filters.value = {
        invoiceCode: '',
        orderCode: '',
        productKeyword: '',
        customerKeyword: '',
        customerId: '',
        taxCodeKeyword: '',
        status: '',
        fromAt: '',
        toAt: '',
        minTotal: '',
        maxTotal: '',
    };
};

const removeFilterTag = (key: string) => {
    if (key in filters.value) {
        (filters.value as Record<string, string>)[key] = '';
    }
};

const toggleExpand = (invoiceId: number) => {
    expandedInvoiceId.value = expandedInvoiceId.value === invoiceId ? null : invoiceId;
};

const remove = (invoiceRef: string | number) => {
    if (!confirm('Bạn có chắc muốn xóa hóa đơn này?')) return;
    router.delete(tenantRoutes.invoices.destroy(invoiceRef));
};

const canExportTax = (invoice: InvoiceRow) => {
    const status = String(invoice.status ?? '').toUpperCase();
    const taxStatus = String(invoice.e_invoice_status ?? 'NOT_EXPORTED').toUpperCase();

    return status === 'ISSUED' && taxStatus !== 'EXPORTED';
};

const markExportTax = (invoiceCode: string) => {
    const ref = String(invoiceCode ?? '').trim();
    if (ref === '') return;

    if (!confirm('Ghi nhận hóa đơn này đã đồng bộ E-Invoices?')) return;

    router.post(tenantRoutes.eInvoices.store(), {
        invoice_refs: [ref],
        mode: 'REALTIME',
        provider: 'MANUAL',
    });
};

const emptyStateMessage = computed(() => {
    if (filters.value.customerId !== '') {
        const name = customers.value.find((row) => String(row.id) === filters.value.customerId)?.name;
        if (name) {
            return `Chưa có hóa đơn nào của khách ${name}.`;
        }
    }

    return 'Không có hóa đơn phù hợp bộ lọc hiện tại.';
});

const isCancelledInvoice = (status: string | null | undefined) => String(status ?? '').toUpperCase() === 'CANCELLED';
</script>

<template>
    <Head title="Danh sách hóa đơn" />
    <TenantLayout>
        <div class="space-y-6 p-6">
            <section class="rounded-2xl border border-border/70 bg-card/95 p-6 shadow-sm backdrop-blur-sm">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">Danh sách hóa đơn</h1>
                        <p class="mt-1 text-sm text-muted-foreground">Theo dõi hóa đơn phát hành, thay thế, hủy và tra cứu chi tiết dòng hàng.</p>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Hóa đơn được tạo tự động từ đơn hàng hoàn thành.
                    </p>
                </div>

                <Alert class="mt-4">
                    <FileText class="size-4" />
                    <AlertTitle>Tra cứu dòng sản phẩm hóa đơn</AlertTitle>
                    <AlertDescription>
                        Bấm vào mã hóa đơn để mở nhanh danh sách sản phẩm, ĐVT, giá thực tế, thuế và thành tiền theo đúng dữ liệu đã xuất.
                    </AlertDescription>
                </Alert>

                <div class="mt-5 grid gap-3 md:grid-cols-4">
                    <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Hóa đơn trên trang</p>
                        <p class="mt-1 text-2xl font-semibold">{{ filteredInvoices.length }}</p>
                    </div>
                    <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Đã phát hành</p>
                        <p class="mt-1 text-2xl font-semibold">{{ filteredSummary.issued }}</p>
                    </div>
                    <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Đã thay thế / hủy</p>
                        <p class="mt-1 text-2xl font-semibold">{{ filteredSummary.replaced + filteredSummary.cancelled }}</p>
                    </div>
                    <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Doanh thu theo lọc</p>
                        <p class="mt-1 text-2xl font-semibold">{{ formatCurrency(filteredSummary.totalAmount) }}</p>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-[320px_minmax(0,1fr)]">
                <aside class="rounded-2xl border border-border/70 bg-card p-4">
                    <div class="mb-3 flex items-center gap-2">
                        <SlidersHorizontal class="size-4 text-muted-foreground" />
                        <h2 class="text-sm font-semibold">Bộ lọc hóa đơn</h2>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <p class="mb-1 text-xs font-medium text-muted-foreground">Mã hóa đơn</p>
                            <FilterSearchInput v-model="filters.invoiceCode" placeholder="Ví dụ: INV-00025" />
                        </div>

                        <div>
                            <p class="mb-1 text-xs font-medium text-muted-foreground">Mã đơn hàng</p>
                            <FilterSearchInput v-model="filters.orderCode" placeholder="Ví dụ: ORD-00112" />
                        </div>

                        <div>
                            <p class="mb-1 text-xs font-medium text-muted-foreground">Sản phẩm hoặc ĐVT</p>
                            <FilterSearchInput v-model="filters.productKeyword" placeholder="Ví dụ: pepsi lon" />
                        </div>

                        <div>
                            <p class="mb-1 text-xs font-medium text-muted-foreground">Khách hàng</p>
                            <FilterSearchInput v-model="filters.customerKeyword" placeholder="Tìm theo tên khách" />
                            <Select
                                :model-value="filters.customerId === '' ? '__all_customer__' : filters.customerId"
                                @update:model-value="(value) => filters.customerId = String(value) === '__all_customer__' ? '' : String(value)"
                            >
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Tất cả khách hàng" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__all_customer__">Tất cả khách hàng</SelectItem>
                                    <SelectItem v-for="customer in customers" :key="customer.id" :value="String(customer.id)">
                                        {{ customer.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <p class="mb-1 text-xs font-medium text-muted-foreground">Mã số thuế khách hàng</p>
                            <FilterSearchInput v-model="filters.taxCodeKeyword" placeholder="Nhập MST cần tra cứu" />
                        </div>

                        <div>
                            <p class="mb-1 text-xs font-medium text-muted-foreground">Trạng thái hóa đơn</p>
                            <Select
                                :model-value="filters.status === '' ? '__all_status__' : filters.status"
                                @update:model-value="(value) => filters.status = String(value) === '__all_status__' ? '' : String(value)"
                            >
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Tất cả trạng thái" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__all_status__">Tất cả trạng thái</SelectItem>
                                    <SelectItem v-for="status in statusOptions.filter((row) => row.value !== '')" :key="status.value" :value="status.value">
                                        {{ status.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <p class="mb-1 text-xs font-medium text-muted-foreground">Từ thời gian xuất</p>
                            <DateTimePicker v-model="filters.fromAt" expand />
                        </div>

                        <div>
                            <p class="mb-1 text-xs font-medium text-muted-foreground">Đến thời gian xuất</p>
                            <DateTimePicker v-model="filters.toAt" expand />
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <p class="mb-1 text-xs font-medium text-muted-foreground">Tổng tiền từ</p>
                                <input
                                    v-model="filters.minTotal"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background"
                                    placeholder="0"
                                >
                            </div>
                            <div>
                                <p class="mb-1 text-xs font-medium text-muted-foreground">Tổng tiền đến</p>
                                <input
                                    v-model="filters.maxTotal"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background"
                                    placeholder="500000"
                                >
                            </div>
                        </div>

                        <FilterResetButton :count="activeFilterTags.length" @reset="resetFilters" />
                        <FilterTagList :tags="activeFilterTags" @remove="removeFilterTag" />

                        <div>
                            <p class="mb-1 text-xs font-medium text-muted-foreground">Số hóa đơn / trang</p>
                            <Select :model-value="selectedPerPage" @update:model-value="(value) => updatePerPage(String(value))">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Chọn số lượng" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="option in perPageOptions" :key="option" :value="String(option)">
                                        {{ option }} hóa đơn
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </aside>

                <div class="rounded-2xl border border-border/70 bg-card p-4">
                    <div class="overflow-x-auto rounded-xl border border-border/70">
                        <table class="min-w-[1050px] w-full text-sm">
                            <thead class="bg-muted/40 text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium">Mã hóa đơn</th>
                                    <th class="px-4 py-3 text-left font-medium">Thời gian xuất</th>
                                    <th class="px-4 py-3 text-left font-medium">Mã đơn hàng</th>
                                    <th class="px-4 py-3 text-left font-medium">Khách hàng</th>
                                    <th class="px-4 py-3 text-right font-medium">Tiền hàng</th>
                                    <th class="px-4 py-3 text-right font-medium">Thuế</th>
                                    <th class="px-4 py-3 text-right font-medium">Tổng thanh toán</th>
                                    <th class="px-4 py-3 text-center font-medium">Trạng thái</th>
                                    <th class="px-4 py-3 text-center font-medium">Hoá đơn điện tử</th>
                                    <th class="px-4 py-3 text-right font-medium"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <ExpandableRow
                                    v-for="invoice in filteredInvoices"
                                    :key="invoice.id"
                                    :expanded="expandedInvoiceId === invoice.id"
                                    :col-span="10"
                                    @toggle="toggleExpand(invoice.id)"
                                >
                                    <template #summary="slotProps">
                                        <td class="px-4 py-3">
                                            <ExpandableRowToggle :expanded="slotProps.expanded" :label="invoice.code" @toggle="slotProps.toggle" />
                                        </td>
                                        <td class="px-4 py-3">{{ formatDateTime(invoice.sale_at || invoice.created_at) }}</td>
                                        <td class="px-4 py-3">{{ invoice.order?.code || '-' }}</td>
                                        <td class="px-4 py-3">{{ invoice.customer?.name || 'Khách lẻ' }}</td>
                                        <td class="px-4 py-3 text-right font-medium">{{ formatCurrency(invoice.subtotal) }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatCurrency(invoice.tax_amount) }}</td>
                                        <td class="px-4 py-3 text-right font-medium">{{ formatCurrency(invoice.total_amount) }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadgeClass(invoice.status)">
                                                {{ getInvoiceStatusText(invoice.status) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span
                                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                                :class="String(invoice.e_invoice_status ?? 'NOT_EXPORTED').toUpperCase() === 'EXPORTED' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700'"
                                            >
                                                {{ String(invoice.e_invoice_status ?? 'NOT_EXPORTED').toUpperCase() === 'EXPORTED' ? 'Đã export' : 'Chưa xuất' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger as-child>
                                                    <Button type="button" variant="outline" size="sm" class="h-8 gap-1.5">
                                                        <MoreHorizontal class="size-3.5" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" class="w-40">
                                                    <DropdownMenuItem as-child>
                                                        <Link :href="tenantRoutes.invoices.show(invoicePublicRef(invoice))">Xem chi tiết</Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem v-if="canExportTax(invoice)" @click="markExportTax(invoice.code)">
                                                        Đánh dấu xuất HĐĐT
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator v-if="isCancelledInvoice(invoice.status)" />
                                                    <DropdownMenuItem v-if="isCancelledInvoice(invoice.status)" class="text-red-600 focus:text-red-600" @click="remove(invoicePublicRef(invoice))">
                                                        Xóa hóa đơn
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </td>
                                    </template>

                                    <template #content>
                                        <InvoiceDetailTab :invoice="invoice" />
                                    </template>
                                </ExpandableRow>

                                <tr v-if="filteredInvoices.length === 0">
                                    <td colspan="10" class="px-4 py-8 text-center text-muted-foreground">{{ emptyStateMessage }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 flex flex-col gap-3 text-sm md:flex-row md:items-center md:justify-between">
                        <p class="text-muted-foreground">
                            Hiển thị {{ props.invoices.from ?? 0 }} - {{ props.invoices.to ?? 0 }} / {{ props.invoices.total }} hóa đơn
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <template v-for="(link, index) in props.invoices.links" :key="`${link.label}-${index}`">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    preserve-scroll
                                    preserve-state
                                    class="rounded-lg border px-3 py-1.5 text-xs md:text-sm"
                                    :class="link.active ? 'border-primary bg-primary text-primary-foreground' : 'border-border/70 bg-background hover:bg-muted'"
                                >
                                    <span v-html="link.label" />
                                </Link>
                                <span
                                    v-else
                                    class="rounded-lg border border-border/60 bg-muted/30 px-3 py-1.5 text-xs text-muted-foreground md:text-sm"
                                    v-html="link.label"
                                />
                            </template>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </TenantLayout>
</template>