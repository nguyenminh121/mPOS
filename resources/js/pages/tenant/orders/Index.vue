<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, MoreHorizontal, SlidersHorizontal } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DateTimePicker from '@/components/DateTimePicker.vue';
import OrderDetailTab from '@/components/tenant/orders/OrderDetailTab.vue';
import ReturnOrderTab from '@/components/tenant/orders/ReturnOrderTab.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ExpandableRow, ExpandableRowToggle } from '@/components/ui/expandable-row';
import { FilterDebtToggle, FilterResetButton, FilterSearchInput, FilterTagList } from '@/components/ui/filter';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { invoicePublicRef } from '@/lib/invoicePublicRef';
import { parseUtc7DateTimeToMs } from '@/lib/invoiceTemplate';
import { orderPublicRef } from '@/lib/orderPublicRef';
import { tenantRoutes } from '@/lib/tenantRoutes';

type OrderItem = {
    id: number;
    quantity?: number | string;
    price?: number | string;
    total?: number | string;
    product_variant_id?: number;
    product_variant?: {
        product_id?: number | null;
        name?: string | null;
        sku?: string | null;
        barcode?: string | null;
        product?: {
            id?: number;
            category?: {
                id: number;
                name: string;
            } | null;
        } | null;
    } | null;
};

type ReturnOrderSummary = {
    id: number;
    code?: string;
    status?: string;
    total_amount?: number | string;
    return_date?: string | null;
    created_at?: string | null;
};

type OrderRow = {
    id: number;
    code: string;
    status: string;
    type?: string | null;
    customer?: {
        id: number;
        name: string;
    } | null;
    warehouse?: {
        id: number;
        name: string;
    } | null;
    user?: {
        id: number;
        name: string;
    } | null;
    surcharges?: Array<{
        id: number;
        name?: string;
        type?: 'PERCENT' | 'FIXED_AMOUNT';
        value?: number | string;
        amount?: number | string;
        is_refundable?: boolean;
    }>;
    invoice_id?: number | null;
    invoice?: {
        id?: number;
        code?: string | null;
        e_invoice_status?: string | null;
    } | null;
    items?: OrderItem[];
    return_orders?: ReturnOrderSummary[];
    returnOrders?: ReturnOrderSummary[];
    note?: string | null;
    order_date?: string | null;
    created_at?: string | null;
    total_amount?: number | string | null;
    discount?: number | string | null;
    paid_amount?: number | string | null;
    debt_amount?: number | string | null;
    final_amount?: number | string | null;
};

type CustomerOption = {
    id: number;
    name: string;
};

type CategoryOption = {
    id: number;
    name: string;
    parent_id?: number | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type OrderPagination = {
    data: OrderRow[];
    current_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
};

const props = defineProps<{
    orders: OrderPagination;
    customers: CustomerOption[];
    categories: CategoryOption[];
    invoicePolicy?: {
        use_invoice_on_sale: boolean;
    };
}>();

const filters = ref({
    orderCode: '',
    productKeyword: '',
    categoryId: '',
    status: '',
    customerKeyword: '',
    customerId: '',
    debtOnly: '',
    fromAt: '',
    toAt: '',
});

const expandedOrderId = ref<number | null>(null);
const detailViewTab = ref<'order' | 'return'>('order');

const toNumber = (value: number | string | null | undefined): number => {
    if (value === null || value === undefined) return 0;
    const parsed = typeof value === 'string' ? parseFloat(value) : Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
};

const formatCurrency = (value: number | string | null | undefined): string => {
    return new Intl.NumberFormat('vi-VN').format(toNumber(value));
};

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

const getOrderStatusText = (status: string): string => {
    const map: Record<string, string> = {
        PREORDER: 'Đặt trước',
        DRAFT: 'Nháp',
        CONFIRMED: 'Đã xác nhận',
        PROCESSING: 'Đang xử lý',
        COMPLETED: 'Hoàn thành',
        CANCELLED: 'Đã hủy',
    };

    return map[status] ?? status;
};

const statusBadgeClass = (status: string): string => {
    const classes: Record<string, string> = {
        PREORDER: 'bg-blue-100 text-blue-700',
        DRAFT: 'bg-slate-100 text-slate-700',
        CONFIRMED: 'bg-indigo-100 text-indigo-700',
        PROCESSING: 'bg-amber-100 text-amber-700',
        COMPLETED: 'bg-emerald-100 text-emerald-700',
        CANCELLED: 'bg-red-100 text-red-700',
    };

    return classes[status] ?? 'bg-slate-100 text-slate-700';
};

const statusOptions = [
    { value: '', label: 'Tất cả trạng thái' },
    { value: 'PREORDER', label: 'Đặt trước' },
    { value: 'DRAFT', label: 'Nháp' },
    { value: 'CONFIRMED', label: 'Đã xác nhận' },
    { value: 'PROCESSING', label: 'Đang xử lý' },
    { value: 'COMPLETED', label: 'Hoàn thành' },
    { value: 'CANCELLED', label: 'Đã hủy' },
];

const customers = computed(() => {
    const keyword = normalizeText(filters.value.customerKeyword);
    if (keyword === '') return props.customers;

    return props.customers.filter((customer) => normalizeText(customer.name).includes(keyword));
});

const categories = computed(() => props.categories);

const categoryTreeOptions = computed(() => {
    const byParent = new Map<number | null, CategoryOption[]>();

    for (const category of props.categories) {
        const parentId = category.parent_id ?? null;
        const bucket = byParent.get(parentId) ?? [];
        bucket.push(category);
        byParent.set(parentId, bucket);
    }

    for (const bucket of byParent.values()) {
        bucket.sort((left, right) => left.name.localeCompare(right.name, 'vi'));
    }

    const result: Array<CategoryOption & { depth: number }> = [];
    const visited = new Set<number>();

    const visit = (parentId: number | null, depth: number) => {
        const children = byParent.get(parentId) ?? [];
        for (const child of children) {
            if (visited.has(child.id)) continue;
            visited.add(child.id);
            result.push({ ...child, depth });
            visit(child.id, depth + 1);
        }
    };

    visit(null, 0);

    for (const category of props.categories) {
        if (visited.has(category.id)) continue;
        result.push({ ...category, depth: 0 });
        visit(category.id, 1);
    }

    return result;
});

const categoryDescendantsById = computed(() => {
    const childrenByParent = new Map<number, number[]>();

    for (const category of props.categories) {
        const parentId = Number(category.parent_id ?? 0);
        if (!Number.isFinite(parentId) || parentId <= 0) continue;

        const bucket = childrenByParent.get(parentId) ?? [];
        bucket.push(category.id);
        childrenByParent.set(parentId, bucket);
    }

    const result = new Map<number, Set<number>>();

    const collect = (categoryId: number): Set<number> => {
        if (result.has(categoryId)) {
            return result.get(categoryId) as Set<number>;
        }

        const collected = new Set<number>([categoryId]);
        const children = childrenByParent.get(categoryId) ?? [];

        for (const childId of children) {
            const childSet = collect(childId);
            for (const value of childSet) {
                collected.add(value);
            }
        }

        result.set(categoryId, collected);
        return collected;
    };

    for (const category of props.categories) {
        collect(category.id);
    }

    return result;
});

const perPageOptions = [10, 20, 50, 100];

const selectedPerPage = computed(() => String(props.orders.per_page || 20));

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

    router.get(tenantRoutes.orders.index(), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const matchesProductKeyword = (order: OrderRow, tokens: string[]) => {
    if (tokens.length === 0) return true;

    return (order.items ?? []).some((item) => {
        const searchable = normalizeText([
            item.product_variant?.name,
            item.product_variant?.sku,
            item.product_variant?.barcode,
        ].join(' '));

        return tokens.every((token) => searchable.includes(token));
    });
};

const inTimeRange = (order: OrderRow) => {
    const orderTime = parseUtc7DateTimeToMs(order.order_date || order.created_at || null);
    if (orderTime === null) return false;

    if (filters.value.fromAt) {
        const fromTime = parseUtc7DateTimeToMs(filters.value.fromAt);
        if (fromTime !== null && orderTime < fromTime) {
            return false;
        }
    }

    if (filters.value.toAt) {
        const toTime = parseUtc7DateTimeToMs(filters.value.toAt);
        if (toTime !== null && orderTime > toTime) {
            return false;
        }
    }

    return true;
};

const filteredOrders = computed(() => {
    const orderCodeKeyword = normalizeText(filters.value.orderCode);
    const productTokens = toTokens(filters.value.productKeyword);
    const customerKeyword = normalizeText(filters.value.customerKeyword);

    return props.orders.data.filter((order) => {
        const matchOrderCode = orderCodeKeyword === '' || normalizeText(order.code).includes(orderCodeKeyword);
        const matchProduct = matchesProductKeyword(order, productTokens);
        const matchStatus = filters.value.status === '' || order.status === filters.value.status;
        const matchCustomerKeyword = customerKeyword === '' || normalizeText(order.customer?.name).includes(customerKeyword);
        const matchCustomer = filters.value.customerId === '' || String(order.customer?.id ?? '') === filters.value.customerId;
        const selectedCategoryId = Number(filters.value.categoryId || 0);
        const categoryScope = selectedCategoryId > 0
            ? (categoryDescendantsById.value.get(selectedCategoryId) ?? new Set<number>([selectedCategoryId]))
            : null;
        const matchCategory = categoryScope === null || (order.items ?? []).some((item) => {
            const itemCategoryId = Number(item.product_variant?.product?.category?.id ?? 0);
            return itemCategoryId > 0 && categoryScope.has(itemCategoryId);
        });
        const matchDebt = filters.value.debtOnly !== 'true' || toNumber(order.debt_amount) > 0;
        const matchTime = inTimeRange(order);

        return matchOrderCode && matchProduct && matchStatus && matchCustomerKeyword && matchCustomer && matchCategory && matchDebt && matchTime;
    });
});

const filteredSummary = computed(() => {
    return filteredOrders.value.reduce((acc, order) => {
        acc.totalFinal += toNumber(order.final_amount);
        acc.totalDebt += toNumber(order.debt_amount);
        if (order.status === 'COMPLETED') {
            acc.completed += 1;
        }
        return acc;
    }, {
        totalFinal: 0,
        totalDebt: 0,
        completed: 0,
    });
});

const activeFilterTags = computed(() => {
    const tags: Array<{ key: string; label: string; value: string }> = [];

    if (filters.value.orderCode.trim() !== '') {
        tags.push({ key: 'orderCode', label: 'Mã đơn', value: filters.value.orderCode.trim() });
    }

    if (filters.value.productKeyword.trim() !== '') {
        tags.push({ key: 'productKeyword', label: 'Tên/SKU/Barcode', value: filters.value.productKeyword.trim() });
    }

    if (filters.value.categoryId !== '') {
        const categoryName = categories.value.find((row) => String(row.id) === filters.value.categoryId)?.name ?? filters.value.categoryId;
        tags.push({ key: 'categoryId', label: 'Danh mục', value: categoryName });
    }

    if (filters.value.status !== '') {
        tags.push({ key: 'status', label: 'Trạng thái', value: getOrderStatusText(filters.value.status) });
    }

    if (filters.value.customerId !== '') {
        const name = props.customers.find((row) => String(row.id) === filters.value.customerId)?.name ?? filters.value.customerId;
        tags.push({ key: 'customerId', label: 'Khách hàng', value: name });
    }

    if (filters.value.debtOnly === 'true') {
        tags.push({ key: 'debtOnly', label: 'Công nợ', value: 'Có nợ' });
    }

    if (filters.value.fromAt !== '') {
        tags.push({ key: 'fromAt', label: 'Từ thời gian', value: formatDateTime(filters.value.fromAt) });
    }

    if (filters.value.toAt !== '') {
        tags.push({ key: 'toAt', label: 'Đến thời gian', value: formatDateTime(filters.value.toAt) });
    }

    return tags;
});

const resetFilters = () => {
    filters.value = {
        orderCode: '',
        productKeyword: '',
        categoryId: '',
        status: '',
        customerKeyword: '',
        customerId: '',
        debtOnly: '',
        fromAt: '',
        toAt: '',
    };
};

const removeFilterTag = (key: string) => {
    if (key in filters.value) {
        (filters.value as Record<string, string>)[key] = '';
    }
};

const toggleExpand = (orderId: number) => {
    if (expandedOrderId.value === orderId) {
        expandedOrderId.value = null;
        return;
    }

    expandedOrderId.value = orderId;
    detailViewTab.value = 'order';
};

const remove = (orderRef: string | number) => {
    if (!confirm('Bạn có chắc muốn xóa đơn hàng này?')) return;
    router.delete(tenantRoutes.orders.destroy(orderRef));
};

const orderEditHref = (orderRef: string) => {
    const query = new URLSearchParams({
        mode: 'RETAIL',
        entry: 'orders-edit',
        editOrderRef: orderRef,
    });

    return `${tenantRoutes.sales.index()}?${query.toString()}`;
};

const emptyStateMessage = computed(() => {
    if (filters.value.customerId !== '') {
        const name = props.customers.find((row) => String(row.id) === filters.value.customerId)?.name;
        if (name) {
            return `Chưa có đơn hàng nào từ khách ${name}.`;
        }
    }

    return 'Không có đơn hàng phù hợp bộ lọc hiện tại.';
});

const isCancelledOrder = (status: string | null | undefined) => String(status ?? '').toUpperCase() === 'CANCELLED';
const isCompletedOrder = (status: string | null | undefined) => String(status ?? '').toUpperCase() === 'COMPLETED';
const canDeleteOrder = (status: string | null | undefined) => !isCancelledOrder(status) && !isCompletedOrder(status);
const canModifyOrderData = (order: OrderRow) => {
    const status = String(order.status ?? '').toUpperCase();

    if (status === 'PREORDER' || status === 'DRAFT') {
        return true;
    }

    if (status === 'COMPLETED') {
        const exported = String(order.invoice?.e_invoice_status ?? 'NOT_EXPORTED').toUpperCase() === 'EXPORTED';
        return !exported;
    }

    return false;
};
const canOpenPayment = (order: OrderRow) => {
    if (isCancelledOrder(order.status)) return false;
    if (toNumber(order.debt_amount) > 0) return true;
    return Boolean(props.invoicePolicy?.use_invoice_on_sale) && isCompletedOrder(order.status) && Number(order.invoice_id || 0) <= 0;
};

const hasReturnOrders = (order: OrderRow) => {
    const fromSnake = order.return_orders ?? [];
    if (fromSnake.length > 0) return true;

    const fromCamel = order.returnOrders ?? [];
    return fromCamel.length > 0;
};

const getReturnCodes = (order: OrderRow): string[] => {
    const rows = [...(order.return_orders ?? []), ...(order.returnOrders ?? [])];
    const codes = rows
        .map((row) => String(row.code ?? '').trim())
        .filter((code) => code !== '');

    return Array.from(new Set(codes));
};
</script>

<template>
    <Head title="Danh sách đơn hàng" />
    <TenantLayout>
        <div class="space-y-6 p-6">
            <section class="rounded-2xl border border-border/70 bg-card/95 p-6 shadow-sm backdrop-blur-sm">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">Danh sách đơn hàng</h1>
                        <p class="mt-1 text-sm text-muted-foreground">Theo dõi trạng thái xử lý, công nợ và chi tiết đơn bán.</p>
                    </div>
                    <Link
                        :href="`${tenantRoutes.sales.index()}?mode=RETAIL&entry=orders-index`"
                        class="inline-flex items-center justify-center rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                    >
                        + Tạo đơn hàng
                    </Link>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-4">
                    <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Tổng đơn trên trang</p>
                        <p class="mt-1 text-2xl font-semibold">{{ filteredOrders.length }}</p>
                    </div>
                    <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Đơn hoàn thành</p>
                        <p class="mt-1 text-2xl font-semibold">{{ filteredSummary.completed }}</p>
                    </div>
                    <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Doanh số (lọc hiện tại)</p>
                        <p class="mt-1 text-2xl font-semibold">{{ formatCurrency(filteredSummary.totalFinal) }}</p>
                    </div>
                    <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Công nợ (lọc hiện tại)</p>
                        <p class="mt-1 text-2xl font-semibold">{{ formatCurrency(filteredSummary.totalDebt) }}</p>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-[260px_minmax(0,1fr)]">
                <aside class="rounded-2xl border border-border/70 bg-card p-4">
                    <div class="mb-3 flex items-center gap-2">
                        <SlidersHorizontal class="size-4 text-muted-foreground" />
                        <h2 class="text-sm font-semibold">Bộ lọc đơn hàng</h2>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <p class="mb-1 text-xs font-medium text-muted-foreground">Mã đơn</p>
                            <FilterSearchInput v-model="filters.orderCode" placeholder="Nhập mã đơn" />
                        </div>

                        <div>
                            <p class="mb-1 text-xs font-medium text-muted-foreground">Tên sản phẩm, SKU, Barcode</p>
                            <FilterSearchInput v-model="filters.productKeyword" placeholder="Ví dụ: sua vinamilk" />
                        </div>

                        <div>
                            <p class="mb-1 text-xs font-medium text-muted-foreground">Danh mục hàng hóa</p>
                            <Select
                                :model-value="filters.categoryId === '' ? '__all_category__' : filters.categoryId"
                                @update:model-value="(value) => filters.categoryId = String(value) === '__all_category__' ? '' : String(value)"
                            >
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Tất cả danh mục" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__all_category__">Tất cả danh mục</SelectItem>
                                    <SelectItem v-for="category in categoryTreeOptions" :key="category.id" :value="String(category.id)">
                                        {{ `${'\u00A0\u00A0'.repeat(category.depth)}${category.depth > 0 ? '↳ ' : ''}${category.name}` }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <p class="mb-1 text-xs font-medium text-muted-foreground">Trạng thái đơn</p>
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
                            <p class="mb-1 text-xs font-medium text-muted-foreground">Khách hàng</p>
                            <FilterSearchInput v-model="filters.customerKeyword" placeholder="Tìm khách hàng theo tên" />
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

                        <FilterDebtToggle v-model="filters.debtOnly" />

                        <div>
                            <p class="mb-1 text-xs font-medium text-muted-foreground">Từ thời gian tạo</p>
                            <DateTimePicker v-model="filters.fromAt" expand />
                        </div>

                        <div>
                            <p class="mb-1 text-xs font-medium text-muted-foreground">Đến thời gian tạo</p>
                            <DateTimePicker v-model="filters.toAt" expand />
                        </div>

                        <FilterResetButton :count="activeFilterTags.length" @reset="resetFilters" />

                        <FilterTagList :tags="activeFilterTags" @remove="removeFilterTag" />

                        <div>
                            <p class="mb-1 text-xs font-medium text-muted-foreground">Số đơn / trang</p>
                            <Select :model-value="selectedPerPage" @update:model-value="(value) => updatePerPage(String(value))">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Chọn số lượng" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="option in perPageOptions" :key="option" :value="String(option)">
                                        {{ option }} đơn
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </aside>

                <div class="rounded-2xl border border-border/70 bg-card p-4">
                    <div class="overflow-x-auto rounded-xl border border-border/70">
                    <table class="min-w-[1280px] w-full text-sm">
                        <thead class="bg-muted/40 text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">Mã đơn</th>
                                <th class="px-4 py-3 text-left font-medium">Mã trả hàng</th>
                                <th class="px-4 py-3 text-left font-medium">Thời gian</th>
                                <th class="px-4 py-3 text-left font-medium">Khách hàng</th>
                                <th class="px-4 py-3 text-right font-medium">Tổng tiền hàng</th>
                                <th class="px-4 py-3 text-right font-medium">Giảm giá</th>
                                <th class="px-4 py-3 text-right font-medium">Tổng sau giảm</th>
                                <th class="px-4 py-3 text-right font-medium">Đã trả</th>
                                <th class="px-4 py-3 text-right font-medium">Công nợ</th>
                                <th class="px-4 py-3 text-center font-medium">Trạng thái</th>
                                <th class="px-4 py-3 text-right font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <ExpandableRow
                                v-for="order in filteredOrders"
                                :key="order.id"
                                :expanded="expandedOrderId === order.id"
                                :col-span="11"
                                @toggle="toggleExpand(order.id)"
                            >
                                <template #summary="slotProps">
                                    <td class="px-4 py-3">
                                        <ExpandableRowToggle :expanded="slotProps.expanded" :label="order.code" @toggle="slotProps.toggle" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <div v-if="getReturnCodes(order).length > 0" class="flex flex-wrap justify-start gap-1">
                                            <span
                                                v-for="code in getReturnCodes(order)"
                                                :key="`${order.id}-${code}`"
                                                class="inline-flex rounded-full border border-border/70 bg-amber-300 px-2 py-0.5 text-[11px] font-medium text-foreground"
                                            >
                                                {{ code }}
                                            </span>
                                        </div>
                                        <span v-else class="text-xs text-muted-foreground">-</span>
                                    </td>
                                    <td class="px-4 py-3">{{ formatDateTime(order.order_date || order.created_at) }}</td>
                                    <td class="px-4 py-3">{{ order.customer?.name || 'Khách lẻ' }}</td>
                                    <td class="px-4 py-3 text-right font-medium">{{ formatCurrency(order.total_amount) }}</td>
                                    <td class="px-4 py-3 text-right text-red-600">{{ formatCurrency(order.discount) }}</td>
                                    <td class="px-4 py-3 text-right font-medium">{{ formatCurrency(order.final_amount) }}</td>
                                    <td class="px-4 py-3 text-right font-medium">{{ formatCurrency(order.paid_amount) }}</td>
                                    <td class="px-4 py-3 text-right" :class="toNumber(order.debt_amount) > 0 ? 'font-medium text-red-600' : 'text-muted-foreground'">
                                        {{ formatCurrency(order.debt_amount) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadgeClass(order.status)">
                                            {{ getOrderStatusText(order.status) }}
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
                                                    <Link :href="tenantRoutes.orders.show(orderPublicRef(order))">Xem chi tiết</Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem v-if="canModifyOrderData(order)" as-child>
                                                    <Link :href="orderEditHref(orderPublicRef(order))">Sửa đơn</Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="isCancelledOrder(order.status) && String(order.invoice?.code ?? '').trim() !== ''"
                                                    as-child
                                                >
                                                    <Link :href="tenantRoutes.invoices.show(invoicePublicRef(order.invoice))">Xem hóa đơn</Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem v-if="canOpenPayment(order)" as-child>
                                                    <Link :href="`${tenantRoutes.orders.show(orderPublicRef(order))}?openPayment=1`">Thanh toán</Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator v-if="canDeleteOrder(order.status)" />
                                                <DropdownMenuItem v-if="canDeleteOrder(order.status)" class="text-red-600 focus:text-red-600" @click="remove(orderPublicRef(order))">
                                                    Xóa đơn
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </td>
                                </template>

                                <template #content>
                                    <div class="rounded-xl border border-border/70 bg-card p-4">
                                        <div class="mb-3 flex flex-wrap items-center gap-2 border-b border-border/70 pb-2">
                                            <button
                                                type="button"
                                                class="rounded-lg px-3 py-1.5 text-sm font-medium"
                                                :class="detailViewTab === 'order' ? 'bg-primary text-primary-foreground' : 'bg-muted/60 text-foreground hover:bg-muted'"
                                                @click="detailViewTab = 'order'"
                                            >
                                                Chi tiết đơn hàng
                                            </button>
                                            <button
                                                v-if="hasReturnOrders(order)"
                                                type="button"
                                                class="rounded-lg px-3 py-1.5 text-sm font-medium"
                                                :class="detailViewTab === 'return' ? 'bg-primary text-primary-foreground' : 'bg-muted/60 text-foreground hover:bg-muted'"
                                                @click="detailViewTab = 'return'"
                                            >
                                                Phiếu trả hàng
                                            </button>
                                        </div>

                                        <OrderDetailTab
                                            v-if="detailViewTab === 'order'"
                                            :order="order"
                                            :invoice-policy="props.invoicePolicy"
                                            :show-return-tab="false"
                                        />
                                        <ReturnOrderTab v-else-if="hasReturnOrders(order)" :order="order" />
                                        <OrderDetailTab v-else :order="order" :invoice-policy="props.invoicePolicy" :show-return-tab="false" />
                                    </div>
                                </template>
                            </ExpandableRow>

                            <tr v-if="filteredOrders.length === 0">
                                <td colspan="11" class="px-4 py-8 text-center text-muted-foreground">{{ emptyStateMessage }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                    <div class="mt-4 flex flex-col gap-3 text-sm md:flex-row md:items-center md:justify-between">
                        <p class="text-muted-foreground">
                            Hiển thị {{ props.orders.from ?? 0 }} - {{ props.orders.to ?? 0 }} / {{ props.orders.total }} đơn hàng
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <template v-for="(link, index) in props.orders.links" :key="`${link.label}-${index}`">
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
