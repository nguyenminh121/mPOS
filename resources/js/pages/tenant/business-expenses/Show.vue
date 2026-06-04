<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Pencil } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

const props = defineProps<{
	expense: {
		id: number;
		code: string;
		name: string;
		description?: string | null;
		category: string;
		amount: number;
		order_id?: number | null;
		supplier_id?: number | null;
		payment_method?: string | null;
		payment_status: 'PENDING' | 'PAID' | 'CANCELLED';
		paid_at?: string | null;
		expense_date?: string | null;
		note?: string | null;
		order_code?: string | null;
		supplier_name?: string | null;
		supplier_code?: string | null;
		created_by_name?: string | null;
		approved_by_name?: string | null;
	};
}>();

const statusLabel = () => {
	switch (props.expense.payment_status) {
		case 'PAID':
			return 'Đã thanh toán';
		case 'CANCELLED':
			return 'Đã hủy';
		default:
			return 'Chờ thanh toán';
	}
};

const statusVariant = () => {
	switch (props.expense.payment_status) {
		case 'PAID':
			return 'default';
		case 'CANCELLED':
			return 'secondary';
		default:
			return 'outline';
	}
};
</script>

<template>
	<Head title="Chi tiết chi phí doanh nghiệp" />
	<TenantLayout>
		<div class="mx-auto max-w-4xl space-y-6">
			<div class="flex items-center justify-between gap-3">
				<div class="flex items-center gap-3">
					<Button as-child variant="outline" size="icon">
						<Link :href="tenantRoutes.businessExpenses.index()"><ArrowLeft class="size-5" /></Link>
					</Button>
					<div>
						<h1 class="text-2xl font-semibold tracking-tight">{{ expense.name }}</h1>
						<p class="text-sm text-muted-foreground">Mã chứng từ: {{ expense.code }}</p>
					</div>
				</div>
				<Button as-child variant="outline">
					<Link :href="tenantRoutes.businessExpenses.edit(expense.id)"><Pencil class="size-4" />Sửa</Link>
				</Button>
			</div>

			<section class="rounded-2xl border border-border/70 bg-card p-5">
				<div class="mb-4 flex flex-wrap items-center gap-2">
					<Badge variant="outline">{{ expense.category }}</Badge>
					<Badge :variant="statusVariant()">{{ statusLabel() }}</Badge>
				</div>

				<div class="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
					<div><span class="text-muted-foreground">Số tiền:</span> {{ expense.amount.toLocaleString('vi-VN') }} đ</div>
					<div><span class="text-muted-foreground">Ngày chi:</span> {{ expense.expense_date || '-' }}</div>
					<div><span class="text-muted-foreground">Phương thức thanh toán:</span> {{ expense.payment_method || '-' }}</div>
					<div><span class="text-muted-foreground">Ngày thanh toán:</span> {{ expense.paid_at || '-' }}</div>
					<div><span class="text-muted-foreground">Đơn hàng tham chiếu:</span> {{ expense.order_code || '-' }}</div>
					<div><span class="text-muted-foreground">Nhà cung cấp:</span> {{ expense.supplier_name || '-' }} {{ expense.supplier_code ? `(${expense.supplier_code})` : '' }}</div>
					<div><span class="text-muted-foreground">Người tạo:</span> {{ expense.created_by_name || '-' }}</div>
					<div><span class="text-muted-foreground">Người duyệt:</span> {{ expense.approved_by_name || '-' }}</div>
					<div class="md:col-span-2"><span class="text-muted-foreground">Mô tả:</span> {{ expense.description || '-' }}</div>
					<div class="md:col-span-2"><span class="text-muted-foreground">Ghi chú:</span> {{ expense.note || '-' }}</div>
				</div>
			</section>
		</div>
	</TenantLayout>
</template>
