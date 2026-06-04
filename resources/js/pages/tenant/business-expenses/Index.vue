<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type ExpenseRow = {
	id: number;
	code: string;
	name: string;
	category: string;
	amount: number;
	payment_method?: string | null;
	payment_status: 'PENDING' | 'PAID' | 'CANCELLED';
	expense_date?: string | null;
	supplier_name?: string | null;
	order_code?: string | null;
};

const props = defineProps<{
	expenses: ExpenseRow[];
}>();

const paidCount = computed(() => props.expenses.filter((item) => item.payment_status === 'PAID').length);
const pendingCount = computed(() => props.expenses.filter((item) => item.payment_status === 'PENDING').length);
const totalAmount = computed(() => props.expenses.reduce((sum, item) => sum + item.amount, 0));

const statusLabelMap: Record<ExpenseRow['payment_status'], string> = {
	PENDING: 'Chờ thanh toán',
	PAID: 'Đã thanh toán',
	CANCELLED: 'Đã hủy',
};

const statusVariant = (status: ExpenseRow['payment_status']) => {
	switch (status) {
		case 'PAID':
			return 'default';
		case 'CANCELLED':
			return 'secondary';
		default:
			return 'outline';
	}
};

const deleteExpense = (id: number) => {
	if (!confirm('Bạn có chắc muốn xóa chi phí doanh nghiệp này?')) return;
	router.delete(tenantRoutes.businessExpenses.destroy(id));
};
</script>

<template>
	<Head title="Chi phí doanh nghiệp" />
	<TenantLayout>
		<div class="space-y-6">
			<section class="rounded-2xl border border-border/70 bg-card/95 p-6 shadow-sm">
				<div class="flex items-center justify-between gap-3">
					<div>
						<h1 class="text-2xl font-semibold tracking-tight">Chi phí doanh nghiệp</h1>
						<p class="mt-1 text-sm text-muted-foreground">Theo dõi chi phí vận hành, mua sắm và các khoản thanh toán nội bộ doanh nghiệp.</p>
					</div>
					<Button as-child>
						<Link :href="tenantRoutes.businessExpenses.create()">
							<Plus class="size-4" />
							Thêm chi phí doanh nghiệp
						</Link>
					</Button>
				</div>

				<div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-4">
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Tổng chứng từ</p>
						<p class="mt-1 text-2xl font-semibold">{{ expenses.length }}</p>
					</div>
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Chờ thanh toán</p>
						<p class="mt-1 text-2xl font-semibold">{{ pendingCount }}</p>
					</div>
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Đã thanh toán</p>
						<p class="mt-1 text-2xl font-semibold">{{ paidCount }}</p>
					</div>
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Tổng tiền</p>
						<p class="mt-1 text-2xl font-semibold">{{ totalAmount.toLocaleString('vi-VN') }} đ</p>
					</div>
				</div>
			</section>

			<section class="overflow-hidden rounded-2xl border border-border/70 bg-card">
				<div class="overflow-x-auto">
					<table class="w-full text-sm">
						<thead class="bg-muted/40 text-left">
							<tr>
								<th class="p-3 font-medium">Mã</th>
								<th class="p-3 font-medium">Tên chi phí</th>
								<th class="p-3 font-medium">Phân loại</th>
								<th class="p-3 text-right font-medium">Số tiền</th>
								<th class="p-3 font-medium">Tham chiếu</th>
								<th class="p-3 text-center font-medium">Trạng thái</th>
								<th class="p-3 text-center font-medium">Thao tác</th>
							</tr>
						</thead>
						<tbody>
							<tr v-if="expenses.length === 0">
								<td colspan="7" class="p-8 text-center text-muted-foreground">Chưa có chi phí doanh nghiệp nào.</td>
							</tr>
							<tr v-for="row in expenses" :key="row.id" class="border-t hover:bg-muted/20">
								<td class="p-3 font-mono text-xs">{{ row.code }}</td>
								<td class="p-3">
									<p class="font-semibold">{{ row.name }}</p>
									<p class="text-xs text-muted-foreground">Ngày chi: {{ row.expense_date || '-' }}</p>
								</td>
								<td class="p-3">{{ row.category }}</td>
								<td class="p-3 text-right font-medium">{{ row.amount.toLocaleString('vi-VN') }} đ</td>
								<td class="p-3 text-xs text-muted-foreground">
									<p>Đơn hàng: {{ row.order_code || '-' }}</p>
									<p>NCC: {{ row.supplier_name || '-' }}</p>
								</td>
								<td class="p-3 text-center">
									<Badge :variant="statusVariant(row.payment_status)">{{ statusLabelMap[row.payment_status] }}</Badge>
								</td>
								<td class="p-3">
									<div class="flex items-center justify-center">
										<DropdownMenu>
											<DropdownMenuTrigger as-child>
												<Button variant="outline" size="icon-sm"><MoreHorizontal class="size-3.5" /></Button>
											</DropdownMenuTrigger>
											<DropdownMenuContent align="end" class="w-44">
												<DropdownMenuItem as-child><Link :href="tenantRoutes.businessExpenses.show(row.id)" class="inline-flex w-full items-center gap-2"><Eye class="size-4" />Xem</Link></DropdownMenuItem>
												<DropdownMenuItem as-child><Link :href="tenantRoutes.businessExpenses.edit(row.id)" class="inline-flex w-full items-center gap-2"><Pencil class="size-4" />Sửa</Link></DropdownMenuItem>
												<DropdownMenuItem class="text-destructive focus:text-destructive" @click="deleteExpense(row.id)"><Trash2 class="size-4" />Xóa</DropdownMenuItem>
											</DropdownMenuContent>
										</DropdownMenu>
									</div>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</section>
		</div>
	</TenantLayout>
</template>
