<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Pencil } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CustomerAddressTab from '@/components/tenant/customers/CustomerAddressTab.vue';
import CustomerDebtTab from '@/components/tenant/customers/CustomerDebtTab.vue';
import CustomerInfoTab from '@/components/tenant/customers/CustomerInfoTab.vue';
import CustomerOrderTab from '@/components/tenant/customers/CustomerOrderTab.vue';
import OrderDetailModal from '@/components/tenant/customers/modals/OrderDetailModal.vue';
import OrderPaymentModal from '@/components/tenant/customers/modals/OrderPaymentModal.vue';
import { Button } from '@/components/ui/button';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

const props = defineProps<{
	customer: {
		id: number;
		code?: string | null;
		customer_ref?: string | number | null;
		name: string;
		type: string;
		email?: string | null;
		phone?: string | null;
		address?: string | null;
		tax_code?: string | null;
		company_name?: string | null;
		debt_amount: number;
		total_spent: number;
		credit_limit: number;
		default_due_days: number;
		description?: string | null;
		group_name?: string | null;
		price_list_name?: string | null;
		current_debt?: number;
		total_sold?: number;
		total_sold_minus_return?: number;
		is_active: boolean;
	};
	addresses: Array<{
		id: number;
		name?: string | null;
		address?: string | null;
		receiver_name?: string | null;
		receiver_phone?: string | null;
		default_address: 'YES' | 'NO';
	}>;
	orders: Array<{
		id: number;
		code: string;
		status: string;
		type?: string | null;
		order_date?: string | null;
		created_at?: string | null;
		created_by?: string | null;
		note?: string | null;
		total_amount?: number;
		discount?: number;
		surcharge?: number;
		final_amount: number;
		paid_amount: number;
		debt_amount: number;
		items?: Array<{
			id: number;
			unit_name?: string | null;
			quantity?: number;
			price?: number;
			discount?: number;
			total?: number;
			product_variant?: {
				id?: number;
				name?: string | null;
				sku?: string | null;
				barcode?: string | null;
			} | null;
		}>;
		return_orders?: Array<{
			id: number;
			code?: string | null;
			status?: string | null;
			total_amount?: number;
			return_date?: string | null;
			created_at?: string | null;
		}>;
	}>;
	returnOrders: Array<{
		id: number;
		order_id?: number | null;
		code: string;
		status: string;
		return_date?: string | null;
		created_at?: string | null;
		total_amount: number;
		created_by?: string | null;
	}>;
	debtEntries: Array<{
		id: number;
		order_id?: number | null;
		type: string;
		status: string;
		order_status?: string | null;
		amount: number;
		balance_after: number;
		order_code?: string | null;
		paid_at?: string | null;
		created_at?: string | null;
		note?: string | null;
	}>;
	paymentMethods: Array<{
		id: number;
		code: string;
		name: string;
		type?: string | null;
		is_default: boolean;
	}>;
	paymentCollectors: Array<{
		id: number;
		name: string;
	}>;
	authUserId: number;
	salesSummary: {
		total_sold: number;
		total_return: number;
		total_sold_minus_return: number;
	};
}>();

const activeTab = ref<'info' | 'address' | 'orders' | 'debt'>('info');
const orderDetailOpen = ref(false);
const selectedOrderId = ref<number | null>(null);
const orderPaymentOpen = ref(false);

const tabClass = (value: 'info' | 'address' | 'orders' | 'debt') => {
	return activeTab.value === value
		? 'border-primary bg-primary/10 text-primary'
		: 'border-border bg-background text-muted-foreground';
};

const currentDebt = computed(() => Number(props.customer.current_debt ?? props.customer.debt_amount ?? 0));

const debtInvoiceOrderIds = computed(() => {
	return new Set(
		props.debtEntries
			.filter((row) => String(row.type ?? '').toUpperCase() === 'INVOICE')
			.map((row) => Number(row.order_id || 0))
			.filter((value) => value > 0),
	);
});

const debtOrders = computed(() => {
	return props.orders
		.filter((order) => debtInvoiceOrderIds.value.has(order.id))
		.map((order) => ({
			id: order.id,
			code: order.code,
			order_date: order.order_date,
			created_at: order.created_at,
			final_amount: Number(order.final_amount || 0),
			paid_amount: Number(order.paid_amount || 0),
			debt_amount: Number(order.debt_amount || 0),
		}));
});

const unpaidDebtOrders = computed(() => debtOrders.value.filter((row) => row.debt_amount > 0));
const paidDebtOrders = computed(() => debtOrders.value.filter((row) => row.debt_amount <= 0 && row.paid_amount > 0));

const selectedOrder = computed(() => {
	if (!selectedOrderId.value) return null;
	return props.orders.find((row) => row.id === selectedOrderId.value) ?? null;
});

const openOrderDetail = (orderId: number) => {
	selectedOrderId.value = orderId;
	orderDetailOpen.value = true;
};

const openOrderPayment = () => {
	orderPaymentOpen.value = true;
};

const customerRef = computed<string | number>(() => {
	const ref = String(props.customer.customer_ref ?? props.customer.code ?? '').trim();
	return ref !== '' ? ref : props.customer.id;
});

const exportDebtFile = (filters: { from_date?: string; to_date?: string; order_code?: string }) => {
	const params = new URLSearchParams();
	if (filters.from_date) params.set('from_date', filters.from_date);
	if (filters.to_date) params.set('to_date', filters.to_date);
	if (filters.order_code) params.set('order_code', filters.order_code);

	const baseUrl = tenantRoutes.customers.debtExport(customerRef.value);
	window.location.href = params.toString() ? `${baseUrl}?${params.toString()}` : baseUrl;
};

const onOrderDetailOpenChange = (value: boolean) => {
	orderDetailOpen.value = value;
	if (!value) {
		selectedOrderId.value = null;
	}
};

const onOrderPaymentOpenChange = (value: boolean) => {
	orderPaymentOpen.value = value;
};
</script>

<template>
	<Head title="Chi tiết khách hàng" />
	<TenantLayout>
		<div class="mx-auto max-w-5xl space-y-6">
			<div class="flex items-center justify-between gap-3">
				<div class="flex items-center gap-3">
					<Button as-child variant="outline" size="icon">
						<Link :href="tenantRoutes.customers.index()"><ArrowLeft class="size-5" /></Link>
					</Button>
					<div>
						<h1 class="text-2xl font-semibold tracking-tight">{{ customer.name }}</h1>
						<p class="text-sm text-muted-foreground">Mã: {{ customer.code || '-' }}</p>
					</div>
				</div>
				<Button as-child variant="outline">
					<Link :href="tenantRoutes.customers.edit(customerRef)">
						<Pencil class="size-4" />Sửa
					</Link>
				</Button>
			</div>

			<section class="rounded-2xl border border-border/70 bg-card p-4">
				<div class="flex flex-wrap gap-2">
					<button type="button" class="rounded-xl border px-3 py-2 text-sm font-medium transition" :class="tabClass('info')" @click="activeTab = 'info'">Thông tin</button>
					<button type="button" class="rounded-xl border px-3 py-2 text-sm font-medium transition" :class="tabClass('address')" @click="activeTab = 'address'">Địa chỉ</button>
					<button type="button" class="rounded-xl border px-3 py-2 text-sm font-medium transition" :class="tabClass('orders')" @click="activeTab = 'orders'">Hoạt động bán hàng</button>
					<button type="button" class="rounded-xl border px-3 py-2 text-sm font-medium transition" :class="tabClass('debt')" @click="activeTab = 'debt'">Công nợ</button>
				</div>
			</section>

			<CustomerInfoTab v-if="activeTab === 'info'" :customer="customer" />
			<CustomerAddressTab v-if="activeTab === 'address'" :customer-id="customerRef" :addresses="addresses" />
			<CustomerOrderTab
				v-if="activeTab === 'orders'"
				:orders="orders"
				:return-orders="returnOrders"
				:debt-entries="debtEntries"
				:sales-summary="salesSummary"
				@open-order="openOrderDetail"
			/>
			<CustomerDebtTab
				v-if="activeTab === 'debt'"
				:current-debt="currentDebt"
				:unpaid-debt-orders="unpaidDebtOrders"
				:debt-entries="debtEntries"
				@open-order="openOrderDetail"
				@open-payment="openOrderPayment"
				@export-debt="exportDebtFile"
			/>

			<OrderDetailModal
				:open="orderDetailOpen"
				:order="selectedOrder"
				@update:open="onOrderDetailOpenChange"
			/>

			<OrderPaymentModal
				:open="orderPaymentOpen"
				:customer="{ id: customer.id, name: customer.name, phone: customer.phone }"
				:orders="unpaidDebtOrders"
				:payment-methods="paymentMethods"
				:collectors="paymentCollectors"
				:auth-user-id="authUserId"
				@update:open="onOrderPaymentOpenChange"
			/>
		</div>
	</TenantLayout>
</template>
