<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import OrderDetailTab from '@/components/tenant/orders/OrderDetailTab.vue';
import PaymentModal from '@/components/tenant/payments/modals/PaymentModal.vue';
import { Button } from '@/components/ui/button';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { orderPublicRef } from '@/lib/orderPublicRef';
import { tenantRoutes } from '@/lib/tenantRoutes';

type OrderItem = {
    id: number;
    product_variant_id?: number;
    quantity?: number | string;
    price?: number | string;
    discount?: number | string;
    tax_rate?: number | string;
    tax_amount?: number | string;
    total?: number | string;
    product_variant?: {
        name?: string;
    } | null;
};

type OrderDetail = {
    id: number;
    code: string;
    status: string;
    type?: string | null;
    note?: string | null;
    order_date?: string | null;
    created_at?: string | null;
    customer?: {
        id?: number;
        name: string;
    } | null;
    warehouse?: {
        id?: number;
        name: string;
    } | null;
    user?: {
        id?: number;
        name: string;
    } | null;
    items?: OrderItem[];
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
    discount?: number | string;
    return_orders?: Array<{
        id: number;
        code?: string;
        status?: string;
        total_amount?: number | string;
        return_date?: string | null;
        created_at?: string | null;
    }>;
    total_amount?: number | string;
    surcharge?: number | string;
    final_amount?: number | string;
    paid_amount?: number | string;
    debt_amount?: number | string;
    processing_at?: string | null;
    confirmed_at?: string | null;
    completed_at?: string | null;
    cancelled_at?: string | null;
    updated_at?: string | null;
};

const props = defineProps<{
    order: OrderDetail;
    invoicePolicy?: {
        use_invoice_on_sale: boolean;
    };
}>();

const isCancelledOrder = (status: string | null | undefined) => String(status ?? '').toUpperCase() === 'CANCELLED';
const currentStatus = computed(() => String(props.order.status ?? '').toUpperCase());

const orderEditHref = computed(() => {
    const query = new URLSearchParams({
        mode: 'RETAIL',
        entry: 'orders-edit',
        editOrderRef: orderPublicRef(props.order),
    });

    return `${tenantRoutes.sales.index()}?${query.toString()}`;
});

const canEditStatus = computed(() => !isCancelledOrder(props.order.status));
const canCancelOrder = computed(() => !isCancelledOrder(props.order.status));
const canModifyOrderData = computed(() => {
    if (currentStatus.value === 'PREORDER' || currentStatus.value === 'DRAFT') {
        return true;
    }

    if (currentStatus.value === 'COMPLETED') {
        const exported = String(props.order.invoice?.e_invoice_status ?? 'NOT_EXPORTED').toUpperCase() === 'EXPORTED';
        return !exported;
    }

    return false;
});

const statusActions = computed<Array<{ status: string; label: string }>>(() => {
    const map: Record<string, Array<{ status: string; label: string }>> = {
        PREORDER: [
            { status: 'PROCESSING', label: 'Chuyển sang Đang chuẩn bị' },
            { status: 'CONFIRMED', label: 'Xác nhận đơn' },
            { status: 'COMPLETED', label: 'Hoàn thành đơn' },
        ],
        DRAFT: [
            { status: 'PROCESSING', label: 'Chuyển sang Đang chuẩn bị' },
            { status: 'CONFIRMED', label: 'Xác nhận đơn' },
            { status: 'COMPLETED', label: 'Hoàn thành đơn' },
        ],
        PROCESSING: [
            { status: 'CONFIRMED', label: 'Xác nhận đơn' },
            { status: 'COMPLETED', label: 'Hoàn thành đơn' },
        ],
        CONFIRMED: [
            { status: 'COMPLETED', label: 'Hoàn thành đơn' },
        ],
        COMPLETED: [],
        CANCELLED: [],
    };

    return map[currentStatus.value] ?? [];
});

const statusDisplayText = computed(() => {
    const map: Record<string, string> = {
        PREORDER: 'Đơn đặt trước',
        DRAFT: 'Đơn nháp',
        PROCESSING: 'Đơn đang xử lý',
        CONFIRMED: 'Đơn đã xác nhận',
        COMPLETED: 'Đơn đã hoàn thành',
        CANCELLED: 'Đơn đã hủy',
    };

    return map[currentStatus.value] ?? props.order.status;
});

const statusBadgeClass = computed(() => {
    const map: Record<string, string> = {
        PREORDER: 'bg-blue-100 text-blue-700',
        DRAFT: 'bg-slate-100 text-slate-700',
        PROCESSING: 'bg-amber-100 text-amber-700',
        CONFIRMED: 'bg-indigo-100 text-indigo-700',
        COMPLETED: 'bg-emerald-100 text-emerald-700',
        CANCELLED: 'bg-red-100 text-red-700',
    };

    return map[currentStatus.value] ?? 'bg-slate-100 text-slate-700';
});

const currentStatusTime = computed(() => {
    const map: Record<string, string | null | undefined> = {
        PREORDER: props.order.order_date,
        DRAFT: props.order.order_date,
        PROCESSING: props.order.processing_at,
        CONFIRMED: props.order.confirmed_at,
        COMPLETED: props.order.completed_at,
        CANCELLED: props.order.cancelled_at,
    };

    return map[currentStatus.value] ?? props.order.updated_at ?? props.order.created_at;
});

const statusForm = useForm({
    status: currentStatus.value,
});

const updateStatus = () => {
    if (!canEditStatus.value || statusForm.processing) return;
    if (statusForm.status === currentStatus.value) return;
    statusForm.put(`${tenantRoutes.orders.update(orderPublicRef(props.order))}?from_show=1`, {
        preserveScroll: true,
    });
};

const applyStatus = (status: string) => {
    statusForm.status = status;
    updateStatus();
};

const cancelOrder = () => {
    if (!canCancelOrder.value || statusForm.processing) return;
    if (!window.confirm('Bạn có chắc muốn hủy đơn hàng này?')) return;

    statusForm.status = 'CANCELLED';
    statusForm.put(`${tenantRoutes.orders.update(orderPublicRef(props.order))}?from_show=1`, {
        preserveScroll: true,
    });
};
const toNumber = (value: number | string | null | undefined) => Number(value ?? 0);
const hasDebt = computed(() => toNumber(props.order.debt_amount) > 0);
const canOpenPayment = computed(() => {
    if (isCancelledOrder(props.order.status)) return false;
    return hasDebt.value;
});

const paymentModalOpen = ref(false);

const openPaymentModal = () => {
    if (!canOpenPayment.value) return;
    paymentModalOpen.value = true;
};

onMounted(() => {
    if (typeof window === 'undefined') return;
    const query = new URLSearchParams(window.location.search);
    if (query.get('openPayment') === '1' && canOpenPayment.value) {
        paymentModalOpen.value = true;
    }
});

const returnOrders = computed(() => props.order.return_orders ?? []);

const totalReturnAmount = computed(() => {
    return returnOrders.value.reduce((sum, row) => sum + Number(row.total_amount ?? 0), 0);
});

const formatCurrency = (value: number | string | null | undefined) => {
    return new Intl.NumberFormat('vi-VN').format(Number(value ?? 0));
};

const formatDate = (value: string | null | undefined) => {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return new Intl.DateTimeFormat('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
};

const returnStatusText = (status: string | null | undefined) => {
    const map: Record<string, string> = {
        PENDING: 'Chờ xử lý',
        APPROVED: 'Đã duyệt',
        REJECTED: 'Từ chối',
        COMPLETED: 'Hoàn thành',
        CANCELLED: 'Đã hủy',
    };
    const key = String(status ?? '').toUpperCase();
    return map[key] ?? (status || '-');
};
</script>

<template>
    <Head :title="`Chi tiết ${props.order.code}`" />
    <TenantLayout>
        <div class="space-y-6 p-6">
            <section class="rounded-2xl border border-border/70 bg-card/95 p-6 shadow-sm backdrop-blur-sm">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">Chi tiết đơn hàng {{ props.order.code }}</h1>
                        <p class="mt-1 text-sm text-muted-foreground">Theo dõi tình trạng xử lý, công nợ và dòng sản phẩm của đơn.</p>
                        <div class="mt-2">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadgeClass">
                                {{ statusDisplayText }}
                            </span>
                            <p class="mt-1 text-xs text-muted-foreground">Thời điểm trạng thái: {{ formatDate(currentStatusTime) }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            v-for="action in statusActions"
                            :key="action.status"
                            type="button"
                            variant="outline"
                            :disabled="statusForm.processing"
                            @click="applyStatus(action.status)"
                        >
                            {{ statusForm.processing ? 'Đang cập nhật...' : action.label }}
                        </Button>
                        <Button v-if="canCancelOrder" type="button" variant="destructive" :disabled="statusForm.processing" @click="cancelOrder">
                            {{ statusForm.processing ? 'Đang hủy...' : 'Hủy đơn hàng' }}
                        </Button>
                        <Button
                            v-if="canModifyOrderData"
                            type="button"
                            variant="outline"
                            as-child
                        >
                            <Link :href="orderEditHref">Sửa đơn</Link>
                        </Button>
                        <Button
                            v-if="canOpenPayment"
                            type="button"
                            variant="outline"
                            class="h-10"
                            @click="openPaymentModal"
                        >
                            Thanh toán đơn hàng
                        </Button>
                        <Link :href="tenantRoutes.orders.index()" class="rounded-xl border border-border/70 bg-background px-4 py-2 text-sm font-medium hover:bg-muted">
                            Quay lại
                        </Link>
                    </div>
                </div>
                <p v-if="statusForm.errors.status" class="mt-2 text-xs text-red-600">{{ statusForm.errors.status }}</p>
            </section>

            <section class="rounded-2xl border border-border/70 bg-card p-5">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-base font-semibold">Đơn trả hàng liên quan</h2>
                        <p class="text-sm text-muted-foreground">Theo dõi các phiếu trả hàng phát sinh từ đơn bán này.</p>
                    </div>
                    <div class="text-right text-sm">
                        <p class="text-muted-foreground">Số phiếu trả: <span class="font-medium text-foreground">{{ returnOrders.length }}</span></p>
                        <p class="text-muted-foreground">Tổng giá trị trả: <span class="font-medium text-foreground">{{ formatCurrency(totalReturnAmount) }}</span></p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-border/70">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">Mã phiếu trả</th>
                                <th class="px-3 py-2 text-left font-medium">Ngày trả</th>
                                <th class="px-3 py-2 text-left font-medium">Trạng thái</th>
                                <th class="px-3 py-2 text-right font-medium">Giá trị</th>
                                <th class="px-3 py-2 text-right font-medium">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in returnOrders" :key="row.id" class="border-t border-border/70">
                                <td class="px-3 py-2 font-medium">{{ row.code || `#${row.id}` }}</td>
                                <td class="px-3 py-2">{{ formatDate(row.return_date || row.created_at) }}</td>
                                <td class="px-3 py-2">{{ returnStatusText(row.status) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatCurrency(row.total_amount) }}</td>
                                <td class="px-3 py-2 text-right">
                                    <Link :href="tenantRoutes.returns.show(row.id)" class="text-primary hover:underline">Mở phiếu trả</Link>
                                </td>
                            </tr>
                            <tr v-if="returnOrders.length === 0">
                                <td colspan="5" class="px-3 py-5 text-center text-muted-foreground">Chưa có phiếu trả hàng nào cho đơn này.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-border/70 bg-card p-5">
                <OrderDetailTab :order="props.order" :invoice-policy="props.invoicePolicy" />
            </section>

            <PaymentModal
                :open="paymentModalOpen"
                :order="props.order"
                :invoice-policy="props.invoicePolicy"
                @update:open="(value) => (paymentModalOpen = value)"
            />
        </div>
    </TenantLayout>
</template>
