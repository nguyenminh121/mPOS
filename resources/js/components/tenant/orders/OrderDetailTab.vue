<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type OrderItem = {
    id: number;
    product_variant_id?: number;
    unit_name?: string | null;
    quantity?: number | string;
    price?: number | string;
    discount?: number | string;
    tax_amount?: number | string;
    total?: number | string;
    product_variant?: {
        product_id?: number | null;
        name?: string | null;
        sku?: string | null;
        barcode?: string | null;
        product?: {
            id?: number;
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

type OrderSurcharge = {
    id: number;
    name?: string;
    type?: 'PERCENT' | 'FIXED_AMOUNT';
    value?: number | string;
    amount?: number | string;
    is_refundable?: boolean;
};

type OrderDetail = {
    id: number;
    code: string;
    status: string;
    type?: string | null;
    note?: string | null;
    order_date?: string | null;
    created_at?: string | null;
    customer?: { id?: number; name: string } | null;
    warehouse?: { id?: number; name: string } | null;
    user?: { id?: number; name: string } | null;
    items?: OrderItem[];
    surcharges?: OrderSurcharge[];
    return_orders?: ReturnOrderSummary[];
    returnOrders?: ReturnOrderSummary[];
    invoice_id?: number | null;
    discount?: number | string | null;
    total_amount?: number | string | null;
    surcharge?: number | string | null;
    final_amount?: number | string | null;
    paid_amount?: number | string | null;
    debt_amount?: number | string | null;
};

const props = defineProps<{
    order: OrderDetail;
    invoicePolicy?: {
        use_invoice_on_sale: boolean;
    };
    showReturnTab?: boolean;
}>();

const activeTab = ref<'order' | 'return'>('order');

const toNumber = (value: number | string | null | undefined): number => {
    if (value === null || value === undefined) return 0;
    const parsed = typeof value === 'string' ? parseFloat(value) : Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
};

const formatCurrency = (value: number | string | null | undefined): string => {
    return new Intl.NumberFormat('vi-VN').format(toNumber(value));
};

const formatDate = (value: string | null | undefined): string => {
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

const showReturnTab = computed(() => {
    if (props.showReturnTab === false) return false;
    return (props.order.type ?? '').toUpperCase() === 'RETURN';
});

const orderTypeText = computed(() => {
    const map: Record<string, string> = {
        RETAIL: 'Bán lẻ',
        WHOLESALE: 'Bán buôn',
        ONLINE: 'Bán online',
        RETURN: 'Trả hàng',
    };

    return map[String(props.order.type ?? '').toUpperCase()] ?? '-';
});

const orderStatusText = computed(() => {
    const map: Record<string, string> = {
        PREORDER: 'Đặt trước',
        DRAFT: 'Nháp',
        CONFIRMED: 'Đã xác nhận',
        PROCESSING: 'Đang xử lý',
        COMPLETED: 'Hoàn thành',
        CANCELLED: 'Đã hủy',
    };

    return map[String(props.order.status ?? '').toUpperCase()] ?? props.order.status;
});

const returnRows = computed<ReturnOrderSummary[]>(() => {
    const fromSnake = props.order.return_orders ?? [];
    if (fromSnake.length > 0) return fromSnake;
    return props.order.returnOrders ?? [];
});

const customerPaid = computed(() => toNumber(props.order.paid_amount));
const changeToCustomer = computed(() => Math.max(0, customerPaid.value - toNumber(props.order.final_amount)));

const productRefFromItem = (item: OrderItem): string | null => {
    const sku = String(item.product_variant?.sku ?? '').trim();
    if (sku !== '') return sku;

    const barcode = String(item.product_variant?.barcode ?? '').trim();
    if (barcode !== '') return barcode;

    return null;
};

watch(showReturnTab, (visible) => {
    if (!visible && activeTab.value === 'return') {
        activeTab.value = 'order';
    }
});
</script>

<template>
    <div class="space-y-4">

        <div v-if="activeTab === 'order'" class="space-y-4">
            <div class="grid gap-3 md:grid-cols-4">
                <div class="rounded-xl border border-border/70 bg-background/50 p-3">
                    <p class="text-xs uppercase text-muted-foreground">Ngày đơn</p>
                    <p class="mt-1 text-sm font-medium">{{ formatDate(order.order_date || order.created_at) }}</p>
                </div>
                <div class="rounded-xl border border-border/70 bg-background/50 p-3">
                    <p class="text-xs uppercase text-muted-foreground">Khách hàng</p>
                    <p class="mt-1 text-sm font-medium">{{ order.customer?.name || 'Khách lẻ' }}</p>
                </div>
                <div class="rounded-xl border border-border/70 bg-background/50 p-3">
                    <p class="text-xs uppercase text-muted-foreground">Kho xuất</p>
                    <p class="mt-1 text-sm font-medium">{{ order.warehouse?.name || '-' }}</p>
                </div>
                <div class="rounded-xl border border-border/70 bg-background/50 p-3">
                    <p class="text-xs uppercase text-muted-foreground">Trạng thái</p>
                    <p class="mt-1 text-sm font-medium">{{ orderStatusText }}</p>
                </div>
                <div class="rounded-xl border border-border/70 bg-background/50 p-3">
                    <p class="text-xs uppercase text-muted-foreground">Loại đơn hàng</p>
                    <p class="mt-1 text-sm font-medium">{{ orderTypeText }}</p>
                </div>
                <div class="rounded-xl border border-border/70 bg-background/50 p-3">
                    <p class="text-xs uppercase text-muted-foreground">Người bán</p>
                    <p class="mt-1 text-sm font-medium">{{ order.user?.name || '-' }}</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-border/70">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Sản phẩm</th>
                            <th class="px-3 py-2 text-left font-medium">ĐVT</th>
                            <th class="px-3 py-2 text-left font-medium">SKU / Barcode</th>
                            <th class="px-3 py-2 text-right font-medium">Số lượng</th>
                            <th class="px-3 py-2 text-right font-medium">Đơn giá</th>
                            <th class="px-3 py-2 text-right font-medium">Giảm giá</th>
                            <th class="px-3 py-2 text-right font-medium">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in order.items || []" :key="item.id" class="border-t border-border/70">
                            <td class="px-3 py-2">
                                <Link
                                    v-if="productRefFromItem(item)"
                                    :href="tenantRoutes.products.show(productRefFromItem(item)!)"
                                    class="font-medium text-primary hover:underline"
                                >
                                    {{ item.product_variant?.name || `#${item.product_variant_id}` }}
                                </Link>
                                <span v-else>{{ item.product_variant?.name || `#${item.product_variant_id}` }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <Link
                                    v-if="productRefFromItem(item)"
                                    :href="tenantRoutes.products.show(productRefFromItem(item)!)"
                                    class="text-primary hover:underline"
                                >
                                    {{ item.unit_name || '-' }}
                                </Link>
                                <span v-else>{{ item.unit_name || '-' }}</span>
                            </td>
                            <td class="px-3 py-2 text-xs text-muted-foreground">
                                {{ item.product_variant?.sku || '-' }} / {{ item.product_variant?.barcode || '-' }}
                            </td>
                            <td class="px-3 py-2 text-right">{{ toNumber(item.quantity) }}</td>
                            <td class="px-3 py-2 text-right">{{ formatCurrency(item.price) }}</td>
                            <td class="px-3 py-2 text-right text-red-600">{{ formatCurrency(item.discount) }}</td>
                            <td class="px-3 py-2 text-right font-semibold">{{ formatCurrency(item.total) }}</td>
                        </tr>
                        <tr v-if="!order.items || order.items.length === 0">
                            <td colspan="7" class="px-3 py-5 text-center text-muted-foreground">Đơn hàng chưa có sản phẩm.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <div class="rounded-xl border border-border/70 bg-background/50 p-4">
                    <p class="text-xs uppercase text-muted-foreground">Ngày bán hàng</p>
                    <p class="mt-1 text-sm font-medium">{{ formatDate(order.order_date || order.created_at) }}</p>

                    <p class="mt-4 text-xs uppercase text-muted-foreground">Ghi chú</p>
                    <p class="mt-1 text-sm">{{ order.note || '-' }}</p>
                </div>
                <div class="rounded-xl border border-border/70 bg-background/50 p-4">
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Tạm tính</span>
                            <span class="font-medium">{{ formatCurrency(order.total_amount) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Giảm giá đơn hàng</span>
                            <span class="font-medium text-red-600">{{ formatCurrency(order.discount) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Phụ thu</span>
                            <span class="font-medium">{{ formatCurrency(order.surcharge) }}</span>
                        </div>
                        <div v-if="order.surcharges?.length" class="rounded-lg border border-border/70 bg-muted/20 p-2 text-xs">
                            <p class="mb-1 font-medium text-foreground">Đang áp dụng phụ thu:</p>
                            <p v-for="s in order.surcharges" :key="s.id" class="text-muted-foreground">
                                {{ s.name || `#${s.id}` }}: {{ formatCurrency(s.amount) }}
                            </p>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Khách đưa</span>
                            <span class="font-medium">{{ formatCurrency(customerPaid) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Tiền trả lại khách</span>
                            <span class="font-medium">{{ formatCurrency(changeToCustomer) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Công nợ</span>
                            <span class="font-medium" :class="toNumber(order.debt_amount) > 0 ? 'text-red-600' : 'text-foreground'">
                                {{ formatCurrency(order.debt_amount) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between border-t border-border/70 pt-2 text-base">
                            <span class="font-semibold">Tổng cộng</span>
                            <span class="font-semibold text-primary">{{ formatCurrency(order.final_amount) }}</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div v-else class="space-y-3">
            <div class="rounded-xl border border-border/70 bg-background/50 p-3 text-sm text-muted-foreground">
                Thông tin trả hàng gắn với đơn này.
            </div>

            <div class="overflow-hidden rounded-xl border border-border/70">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Mã trả hàng</th>
                            <th class="px-3 py-2 text-left font-medium">Ngày</th>
                            <th class="px-3 py-2 text-left font-medium">Trạng thái</th>
                            <th class="px-3 py-2 text-right font-medium">Giá trị</th>
                            <th class="px-3 py-2 text-right font-medium">Liên kết</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in returnRows" :key="row.id" class="border-t border-border/70">
                            <td class="px-3 py-2 font-medium">{{ row.code || `#${row.id}` }}</td>
                            <td class="px-3 py-2">{{ formatDate(row.return_date || row.created_at) }}</td>
                            <td class="px-3 py-2">{{ row.status || '-' }}</td>
                            <td class="px-3 py-2 text-right">{{ formatCurrency(row.total_amount) }}</td>
                            <td class="px-3 py-2 text-right">
                                <Link :href="tenantRoutes.returns.show(row.id)" class="text-primary hover:underline">Mở phiếu trả</Link>
                            </td>
                        </tr>
                        <tr v-if="returnRows.length === 0">
                            <td colspan="5" class="px-3 py-5 text-center text-muted-foreground">Chưa có dữ liệu trả hàng cho đơn này.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
