<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { orderPublicRef } from '@/lib/orderPublicRef';
import { tenantRoutes } from '@/lib/tenantRoutes';

type PaymentOrder = {
	id: number;
	code: string;
	status: string;
	customer_id?: number | null;
	customer?: { id?: number; name?: string | null } | null;
	invoice_id?: number | null;
	final_amount?: number | string | null;
	paid_amount?: number | string | null;
	debt_amount?: number | string | null;
};

const props = defineProps<{
	open: boolean;
	order: PaymentOrder;
	invoicePolicy?: { use_invoice_on_sale?: boolean };
}>();

const emit = defineEmits<{
	'update:open': [value: boolean];
}>();

const toNumber = (value: number | string | null | undefined) => Number(value ?? 0);
const formatCurrency = (value: number | string | null | undefined) => new Intl.NumberFormat('vi-VN').format(toNumber(value));

const currentStatus = computed(() => String(props.order.status ?? '').toUpperCase());
const hasCustomer = computed(() => Number(props.order.customer_id ?? props.order.customer?.id ?? 0) > 0);
const basePaid = computed(() => Math.max(0, toNumber(props.order.paid_amount)));
const baseFinal = computed(() => Math.max(0, toNumber(props.order.final_amount)));
const baseDebt = computed(() => Math.max(0, toNumber(props.order.debt_amount)));
const invoiceExists = computed(() => Number(props.order.invoice_id || 0) > 0);
const canChooseInvoice = computed(() => Boolean(props.invoicePolicy?.use_invoice_on_sale) && !invoiceExists.value && currentStatus.value === 'COMPLETED');

const collectAmount = ref(0);
const shouldCreateInvoiceNow = ref(false);

const paidAfter = computed(() => Math.max(0, basePaid.value + Math.max(0, collectAmount.value)));
const debtAfter = computed(() => Math.max(0, baseFinal.value - paidAfter.value));
const requiresFullSettlementWithoutCustomer = computed(() => !hasCustomer.value && baseDebt.value > 0);
const invalidWithoutCustomer = computed(() => {
	if (!requiresFullSettlementWithoutCustomer.value) return false;
	return collectAmount.value > 0 && collectAmount.value < baseDebt.value;
});

const canSubmit = computed(() => {
	if (invalidWithoutCustomer.value) return false;
	return collectAmount.value > 0 || (canChooseInvoice.value && shouldCreateInvoiceNow.value);
});

const errorMap = computed<Record<string, string>>(() => form.errors as Record<string, string>);

watch(
	() => collectAmount.value,
	(value) => {
		const normalized = Math.max(0, Math.min(Number(value || 0), baseDebt.value));
		if (normalized !== value) {
			collectAmount.value = normalized;
		}
	},
);

watch(
	() => props.open,
	(open) => {
		if (!open) return;
		collectAmount.value = 0;
		shouldCreateInvoiceNow.value = false;
		form.reset();
		form.clearErrors();
	},
);

const form = useForm({
	status: String(props.order.status ?? '').toUpperCase(),
	paid_amount: basePaid.value,
	invoice_export_mode: 'BATCH' as 'NOW' | 'BATCH',
});

const submit = () => {
	if (!canSubmit.value || form.processing) return;

	const invoiceMode: 'NOW' | 'BATCH' = canChooseInvoice.value && shouldCreateInvoiceNow.value ? 'NOW' : 'BATCH';

	form.transform(() => ({
		status: String(props.order.status ?? '').toUpperCase(),
		paid_amount: collectAmount.value > 0 ? paidAfter.value : undefined,
		invoice_export_mode: canChooseInvoice.value ? invoiceMode : undefined,
	}));

	form.put(`${tenantRoutes.orders.update(orderPublicRef(props.order))}?from_payment=1`, {
		preserveScroll: true,
		onSuccess: () => {
			emit('update:open', false);
		},
	});
};
</script>

<template>
	<Dialog :open="open" @update:open="(value) => emit('update:open', value)">
		<DialogContent class="sm:max-w-xl">
			<DialogHeader>
				<DialogTitle>Thanh toán đơn hàng {{ props.order.code }}</DialogTitle>
				<DialogDescription>
					Thu tiền công nợ và quyết định xuất hóa đơn tại một nơi.
				</DialogDescription>
			</DialogHeader>

			<div class="space-y-4">
				<div class="grid gap-2 rounded-xl border border-border/70 bg-background/60 p-3 text-sm md:grid-cols-3">
					<div>
						<p class="text-xs text-muted-foreground">Khách hàng</p>
						<p class="font-medium">{{ props.order.customer?.name || 'Khách lẻ' }}</p>
					</div>
					<div>
						<p class="text-xs text-muted-foreground">Đã thanh toán</p>
						<p class="font-medium">{{ formatCurrency(basePaid) }}</p>
					</div>
					<div>
						<p class="text-xs text-muted-foreground">Công nợ hiện tại</p>
						<p class="font-medium" :class="baseDebt > 0 ? 'text-red-600' : 'text-emerald-600'">{{ formatCurrency(baseDebt) }}</p>
					</div>
				</div>

				<div class="space-y-2">
					<Label>Thu thêm công nợ</Label>
					<Input v-model.number="collectAmount" type="number" min="0" :max="baseDebt" step="1000" class="text-right" />
					<p class="text-xs text-muted-foreground">Sau khi thu: đã thanh toán {{ formatCurrency(paidAfter) }}, công nợ còn {{ formatCurrency(debtAfter) }}.</p>
					<p v-if="invalidWithoutCustomer" class="text-xs text-amber-600">Đơn chưa gắn khách hàng nên cần thanh toán hết công nợ trong một lần.</p>
				</div>

				<div v-if="canChooseInvoice" class="space-y-2">
					<Label>Xuất hóa đơn</Label>
					<div class="flex gap-2">
						<Button type="button" variant="outline" :class="!shouldCreateInvoiceNow ? 'border-primary text-primary' : ''" @click="shouldCreateInvoiceNow = false">
							Chưa xuất, để cuối ngày
						</Button>
						<Button type="button" variant="outline" :class="shouldCreateInvoiceNow ? 'border-primary text-primary' : ''" @click="shouldCreateInvoiceNow = true">
							Xuất ngay
						</Button>
					</div>
					<p class="text-xs text-muted-foreground">Nếu đã có hóa đơn thì tùy chọn này không hiển thị nữa.</p>
				</div>

				<Alert v-if="!canSubmit" class="border-border/70">
					<AlertTitle>Chưa có thay đổi</AlertTitle>
					<AlertDescription>
						Nhập số tiền thu thêm hoặc chọn xuất hóa đơn ngay để tiếp tục.
					</AlertDescription>
				</Alert>

				<p v-if="form.errors.paid_amount" class="text-xs text-red-600">{{ form.errors.paid_amount }}</p>
				<p v-if="errorMap.customer_id" class="text-xs text-red-600">{{ errorMap.customer_id }}</p>
				<p v-if="errorMap.order" class="text-xs text-red-600">{{ errorMap.order }}</p>
				<p v-if="form.errors.invoice_export_mode" class="text-xs text-red-600">{{ form.errors.invoice_export_mode }}</p>
				<p v-if="form.errors.status" class="text-xs text-red-600">{{ form.errors.status }}</p>
			</div>

			<DialogFooter class="gap-2">
				<Button type="button" variant="outline" @click="emit('update:open', false)">Đóng</Button>
				<Button type="button" :disabled="!canSubmit || form.processing" @click="submit">
					{{ form.processing ? 'Đang lưu...' : 'Xác nhận thanh toán' }}
				</Button>
			</DialogFooter>
		</DialogContent>
	</Dialog>
</template>
