<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type CustomerRow = {
	id: number;
	code?: string | null;
	customer_ref?: string | number | null;
	name: string;
	type: string;
	phone?: string | null;
	email?: string | null;
	debt_amount: number;
	current_debt?: number;
	total_spent: number;
	total_sold?: number;
	total_return?: number;
	total_sold_minus_return?: number;
	display_address?: string | null;
	group_name?: string | null;
	price_list_name?: string | null;
	is_active: boolean;
};

const props = defineProps<{
	customers: CustomerRow[];
	filters?: {
		group_id?: number | null;
		type?: string;
		address?: string;
	};
}>();

const selectedGroup = ref<string>(props.filters?.group_id ? String(props.filters.group_id) : '');
const selectedType = ref<string>(String(props.filters?.type ?? ''));
const addressQuery = ref<string>(String(props.filters?.address ?? ''));

const normalizeText = (value: string | null | undefined): string => String(value ?? '').trim().toLowerCase();

const customerGroups = computed(() => {
	const names = Array.from(new Set(props.customers.map((row) => (row.group_name ?? '').trim()).filter((name) => name !== '')));
	return names.sort((a, b) => a.localeCompare(b, 'vi'));
});

const customerTypes = computed(() => {
	const types = Array.from(new Set(props.customers.map((row) => String(row.type ?? '').trim()).filter((value) => value !== '')));
	return types.sort((a, b) => a.localeCompare(b, 'vi'));
});

const filteredCustomers = computed(() => {
	const group = normalizeText(selectedGroup.value);
	const type = normalizeText(selectedType.value);
	const address = normalizeText(addressQuery.value);

	return props.customers.filter((row) => {
		const matchGroup = group === '' || normalizeText(row.group_name) === group;
		const matchType = type === '' || normalizeText(row.type) === type;
		const matchAddress = address === '' || normalizeText(row.display_address).includes(address);

		return matchGroup && matchType && matchAddress;
	});
});

const summary = computed(() => {
	return filteredCustomers.value.reduce(
		(acc, row) => {
			const currentDebt = Number(row.current_debt ?? row.debt_amount ?? 0);
			const totalSold = Number(row.total_sold ?? row.total_spent ?? 0);
			const soldMinusReturn = Number(row.total_sold_minus_return ?? Math.max(0, totalSold - Number(row.total_return ?? 0)));

			acc.currentDebt += currentDebt;
			acc.totalSold += totalSold;
			acc.totalSoldMinusReturn += soldMinusReturn;

			return acc;
		},
		{ currentDebt: 0, totalSold: 0, totalSoldMinusReturn: 0 },
	);
});

const activeCount = computed(() => filteredCustomers.value.filter((customer) => customer.is_active).length);
const withGroupCount = computed(() => filteredCustomers.value.filter((customer) => !!customer.group_name).length);
const resetFilters = () => {
	selectedGroup.value = '';
	selectedType.value = '';
	addressQuery.value = '';
};

const customerRef = (row: CustomerRow): string | number => {
	const ref = String(row.customer_ref ?? row.code ?? '').trim();
	return ref !== '' ? ref : row.id;
};

const deleteCustomer = (ref: string | number) => {
	if (!confirm('Bạn có chắc muốn xóa khách hàng này?')) return;
	router.delete(tenantRoutes.customers.destroy(ref));
};

const typeLabelMap: Record<string, string> = {
	retail: 'Bán lẻ',
	wholesale: 'Bán sỉ',
	company: 'Doanh nghiệp',
};

const customerTypeLabel = (type: string) => typeLabelMap[type] ?? type;

const formatCurrency = (value: number | string | null | undefined) => Number(value || 0).toLocaleString('vi-VN');

const contactLabel = (row: CustomerRow): string => {
	const phone = String(row.phone ?? '').trim();
	const email = String(row.email ?? '').trim();

	if (phone !== '' && email !== '') {
		return `${phone} | ${email}`;
	}

	if (phone !== '') {
		return phone;
	}

	if (email !== '') {
		return email;
	}

	return '-';
};
</script>

<template>
	<Head title="Khách hàng" />
	<TenantLayout>
		<div class="space-y-6">
			<section class="rounded-2xl border border-border/70 bg-card/95 p-6 shadow-sm">
				<div class="flex items-center justify-between gap-3">
					<div>
						<h1 class="text-2xl font-semibold tracking-tight">Khách hàng</h1>
						<p class="mt-1 text-sm text-muted-foreground">Quản lý khách hàng lẻ, khách hàng thuộc nhóm và bảng giá áp dụng.</p>
					</div>
					<Button as-child>
						<Link :href="tenantRoutes.customers.create()">
							<Plus class="size-4" />
							Thêm khách hàng
						</Link>
					</Button>
				</div>

				<div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Tổng công nợ hiện tại</p>
						<p class="mt-1 text-2xl font-semibold">{{ formatCurrency(summary.currentDebt) }}</p>
					</div>
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Tổng bán</p>
						<p class="mt-1 text-2xl font-semibold">{{ formatCurrency(summary.totalSold) }}</p>
					</div>
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Tổng bán trừ trả hàng</p>
						<p class="mt-1 text-2xl font-semibold">{{ formatCurrency(summary.totalSoldMinusReturn) }}</p>
					</div>
				</div>

				<div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Khách hàng (sau lọc)</p>
						<p class="mt-1 text-2xl font-semibold">{{ filteredCustomers.length }}</p>
					</div>
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Đang hoạt động</p>
						<p class="mt-1 text-2xl font-semibold">{{ activeCount }}</p>
					</div>
					<div class="rounded-xl border border-border/70 bg-background/60 p-4">
						<p class="text-xs uppercase tracking-wide text-muted-foreground">Có nhóm khách hàng</p>
						<p class="mt-1 text-2xl font-semibold">{{ withGroupCount }}</p>
					</div>
				</div>

				<div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-4">
					<div>
						<p class="mb-1 text-xs text-muted-foreground">Nhóm khách hàng</p>
						<select v-model="selectedGroup" class="h-10 w-full rounded-xl border border-border/70 bg-background px-3 text-sm">
							<option value="">Tất cả nhóm</option>
							<option v-for="group in customerGroups" :key="group" :value="group">{{ group }}</option>
						</select>
					</div>
					<div>
						<p class="mb-1 text-xs text-muted-foreground">Loại khách hàng</p>
						<select v-model="selectedType" class="h-10 w-full rounded-xl border border-border/70 bg-background px-3 text-sm">
							<option value="">Tất cả loại</option>
							<option v-for="type in customerTypes" :key="type" :value="type">{{ customerTypeLabel(type) }}</option>
						</select>
					</div>
					<div>
						<p class="mb-1 text-xs text-muted-foreground">Địa chỉ</p>
						<Input v-model="addressQuery" placeholder="Lọc theo địa chỉ" />
					</div>
					<div class="flex items-end">
						<Button type="button" variant="outline" class="h-10 w-full" @click="resetFilters">Xóa lọc</Button>
					</div>
				</div>
			</section>

			<section class="overflow-hidden rounded-2xl border border-border/70 bg-card">
				<div class="overflow-x-auto">
					<table class="w-full text-sm">
						<thead class="bg-muted/40 text-left">
							<tr>
								<th class="p-3 font-medium">Mã khách hàng</th>
								<th class="p-3 font-medium">Tên khách hàng</th>
								<th class="p-3 font-medium">Điện thoại | Email</th>
								<th class="p-3 text-right font-medium">Nợ hiện tại</th>
								<th class="p-3 text-right font-medium">Tổng bán</th>
								<th class="p-3 text-right font-medium">Tổng bán trừ trả hàng</th>
								<th class="p-3 text-center font-medium">Trạng thái</th>
								<th class="p-3 text-center font-medium">Thao tác</th>
							</tr>
						</thead>
						<tbody>
							<tr v-if="filteredCustomers.length === 0">
								<td colspan="8" class="p-8 text-center text-muted-foreground">Chưa có khách hàng nào.</td>
							</tr>
							<tr v-for="row in filteredCustomers" :key="row.id" class="border-t hover:bg-muted/20">
								<td class="p-3 font-mono text-xs">{{ row.code || '-' }}</td>
								<td class="p-3">
									<p class="font-semibold">{{ row.name }}</p>
									<p class="text-xs text-muted-foreground">{{ customerTypeLabel(row.type) }}{{ row.group_name ? ` • ${row.group_name}` : '' }}</p>
								</td>
								<td class="p-3 text-muted-foreground">{{ contactLabel(row) }}</td>
								<td class="p-3 text-right" :class="Number(row.current_debt ?? row.debt_amount ?? 0) > 0 ? 'font-semibold text-red-600' : 'font-medium text-muted-foreground'">
									{{ formatCurrency(row.current_debt ?? row.debt_amount) }}
								</td>
								<td class="p-3 text-right font-medium">{{ formatCurrency(row.total_sold ?? row.total_spent) }}</td>
								<td class="p-3 text-right font-semibold">{{ formatCurrency(row.total_sold_minus_return ?? row.total_spent) }}</td>
								<td class="p-3 text-center">
									<Badge :variant="row.is_active ? 'default' : 'secondary'">{{ row.is_active ? 'Hoạt động' : 'Tắt' }}</Badge>
								</td>
								<td class="p-3">
									<div class="flex items-center justify-center">
										<DropdownMenu>
											<DropdownMenuTrigger as-child>
												<Button variant="outline" size="icon-sm">
													<MoreHorizontal class="size-3.5" />
												</Button>
											</DropdownMenuTrigger>
											<DropdownMenuContent align="end" class="w-44">
												<DropdownMenuItem as-child>
													<Link :href="tenantRoutes.customers.show(customerRef(row))" class="inline-flex w-full items-center gap-2">
														<Eye class="size-4" />Xem
													</Link>
												</DropdownMenuItem>
												<DropdownMenuItem as-child>
													<Link :href="tenantRoutes.customers.edit(customerRef(row))" class="inline-flex w-full items-center gap-2">
														<Pencil class="size-4" />Sửa
													</Link>
												</DropdownMenuItem>
												<DropdownMenuItem class="text-destructive focus:text-destructive" @click="deleteCustomer(customerRef(row))">
													<Trash2 class="size-4" />Xóa
												</DropdownMenuItem>
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
