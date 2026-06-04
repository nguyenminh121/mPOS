<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, Save } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuLabel,
	DropdownMenuRadioGroup,
	DropdownMenuRadioItem,
	DropdownMenuSeparator,
	DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type ExpensePayload = {
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
	expense_date?: string | null;
	note?: string | null;
};

type OrderOption = {
	id: number;
	code: string;
};

type SupplierOption = {
	id: number;
	code?: string | null;
	name: string;
};

const props = defineProps<{
	mode: 'create' | 'edit';
	expense: ExpensePayload | null;
	orderOptions: OrderOption[];
	supplierOptions: SupplierOption[];
}>();

const isEdit = computed(() => props.mode === 'edit');

const form = useForm({
	name: props.expense?.name ?? '',
	description: props.expense?.description ?? '',
	category: props.expense?.category ?? '',
	amount: props.expense?.amount ?? 0,
	order_id: props.expense?.order_id ?? null,
	supplier_id: props.expense?.supplier_id ?? null,
	payment_method: props.expense?.payment_method ?? '',
	payment_status: props.expense?.payment_status ?? 'PENDING',
	expense_date: props.expense?.expense_date ?? '',
	note: props.expense?.note ?? '',
});

const paymentStatusMenu = computed({
	get: () => form.payment_status,
	set: (value: string) => {
		form.payment_status = value as 'PENDING' | 'PAID' | 'CANCELLED';
	},
});

const paymentStatusLabel = computed(() => {
	switch (form.payment_status) {
		case 'PAID':
			return 'Đã thanh toán';
		case 'CANCELLED':
			return 'Đã hủy';
		default:
			return 'Chờ thanh toán';
	}
});

const orderMenuValue = computed({
	get: () => (form.order_id === null ? '' : String(form.order_id)),
	set: (value: string) => {
		form.order_id = value === '' ? null : Number(value);
	},
});

const orderLabel = computed(() => {
	if (form.order_id === null) return 'Không liên kết';
	const selected = props.orderOptions.find((item) => item.id === form.order_id);
	return selected?.code ?? 'Không liên kết';
});

const supplierMenuValue = computed({
	get: () => (form.supplier_id === null ? '' : String(form.supplier_id)),
	set: (value: string) => {
		form.supplier_id = value === '' ? null : Number(value);
	},
});

const supplierLabel = computed(() => {
	if (form.supplier_id === null) return 'Không liên kết';
	const selected = props.supplierOptions.find((item) => item.id === form.supplier_id);
	if (!selected) return 'Không liên kết';

	return selected.code ? `${selected.name} (${selected.code})` : selected.name;
});

const submit = () => {
	if (isEdit.value && props.expense) {
		form.put(tenantRoutes.businessExpenses.update(props.expense.id));
		return;
	}

	form.post(tenantRoutes.businessExpenses.store());
};
</script>

<template>
	<Head :title="isEdit ? 'Sửa chi phí doanh nghiệp' : 'Thêm chi phí doanh nghiệp'" />
	<TenantLayout>
		<div class="mx-auto max-w-5xl space-y-6">
			<div class="flex items-center gap-3">
				<Button as-child variant="outline" size="icon">
					<Link :href="tenantRoutes.businessExpenses.index()"><ArrowLeft class="size-5" /></Link>
				</Button>
				<div>
					<h1 class="text-2xl font-semibold tracking-tight">{{ isEdit ? 'Sửa chi phí doanh nghiệp' : 'Thêm chi phí doanh nghiệp' }}</h1>
					<p class="text-sm text-muted-foreground">Lưu chứng từ chi phí phục vụ vận hành doanh nghiệp và kiểm soát thanh toán.</p>
				</div>
			</div>

			<form class="grid grid-cols-1 gap-4 rounded-2xl border border-border/70 bg-card p-5 md:grid-cols-2" @submit.prevent="submit">
				<div>
					<Label class="mb-2">Tên chi phí *</Label>
					<Input v-model="form.name" placeholder="VD: Chi phí điện tháng 03" />
					<p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
				</div>

				<div>
					<Label class="mb-2">Phân loại *</Label>
					<Input v-model="form.category" placeholder="VD: Vận hành, Marketing, Lương" />
					<p v-if="form.errors.category" class="mt-1 text-xs text-destructive">{{ form.errors.category }}</p>
				</div>

				<div>
					<Label class="mb-2">Số tiền *</Label>
					<Input v-model.number="form.amount" type="number" min="0" step="0.01" />
					<p v-if="form.errors.amount" class="mt-1 text-xs text-destructive">{{ form.errors.amount }}</p>
				</div>

				<div>
					<Label class="mb-2">Ngày chi</Label>
					<Input v-model="form.expense_date" type="date" />
					<p v-if="form.errors.expense_date" class="mt-1 text-xs text-destructive">{{ form.errors.expense_date }}</p>
				</div>

				<div>
					<Label class="mb-2">Trạng thái thanh toán *</Label>
					<DropdownMenu>
						<DropdownMenuTrigger as-child>
							<Button type="button" variant="outline" class="w-full justify-between">
								{{ paymentStatusLabel }}
								<ChevronDown class="size-4 text-muted-foreground" />
							</Button>
						</DropdownMenuTrigger>
						<DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-56">
							<DropdownMenuLabel>Trạng thái thanh toán</DropdownMenuLabel>
							<DropdownMenuSeparator />
							<DropdownMenuRadioGroup v-model="paymentStatusMenu">
								<DropdownMenuRadioItem value="PENDING">Chờ thanh toán</DropdownMenuRadioItem>
								<DropdownMenuRadioItem value="PAID">Đã thanh toán</DropdownMenuRadioItem>
								<DropdownMenuRadioItem value="CANCELLED">Đã hủy</DropdownMenuRadioItem>
							</DropdownMenuRadioGroup>
						</DropdownMenuContent>
					</DropdownMenu>
					<p v-if="form.errors.payment_status" class="mt-1 text-xs text-destructive">{{ form.errors.payment_status }}</p>
				</div>

				<div>
					<Label class="mb-2">Phương thức thanh toán</Label>
					<Input v-model="form.payment_method" placeholder="VD: Chuyển khoản" />
					<p v-if="form.errors.payment_method" class="mt-1 text-xs text-destructive">{{ form.errors.payment_method }}</p>
				</div>

				<div>
					<Label class="mb-2">Liên kết đơn hàng</Label>
					<DropdownMenu>
						<DropdownMenuTrigger as-child>
							<Button type="button" variant="outline" class="w-full justify-between">
								<span class="truncate text-left">{{ orderLabel }}</span>
								<ChevronDown class="size-4 text-muted-foreground" />
							</Button>
						</DropdownMenuTrigger>
						<DropdownMenuContent class="max-h-72 w-[var(--reka-dropdown-menu-trigger-width)] min-w-60 overflow-y-auto">
							<DropdownMenuLabel>Chọn đơn hàng</DropdownMenuLabel>
							<DropdownMenuSeparator />
							<DropdownMenuRadioGroup v-model="orderMenuValue">
								<DropdownMenuRadioItem value="">Không liên kết</DropdownMenuRadioItem>
								<DropdownMenuRadioItem v-for="order in orderOptions" :key="order.id" :value="String(order.id)">{{ order.code }}</DropdownMenuRadioItem>
							</DropdownMenuRadioGroup>
						</DropdownMenuContent>
					</DropdownMenu>
					<p v-if="form.errors.order_id" class="mt-1 text-xs text-destructive">{{ form.errors.order_id }}</p>
				</div>

				<div>
					<Label class="mb-2">Liên kết nhà cung cấp</Label>
					<DropdownMenu>
						<DropdownMenuTrigger as-child>
							<Button type="button" variant="outline" class="w-full justify-between">
								<span class="truncate text-left">{{ supplierLabel }}</span>
								<ChevronDown class="size-4 text-muted-foreground" />
							</Button>
						</DropdownMenuTrigger>
						<DropdownMenuContent class="max-h-72 w-[var(--reka-dropdown-menu-trigger-width)] min-w-60 overflow-y-auto">
							<DropdownMenuLabel>Chọn nhà cung cấp</DropdownMenuLabel>
							<DropdownMenuSeparator />
							<DropdownMenuRadioGroup v-model="supplierMenuValue">
								<DropdownMenuRadioItem value="">Không liên kết</DropdownMenuRadioItem>
								<DropdownMenuRadioItem v-for="supplier in supplierOptions" :key="supplier.id" :value="String(supplier.id)">
									{{ supplier.name }} {{ supplier.code ? `(${supplier.code})` : '' }}
								</DropdownMenuRadioItem>
							</DropdownMenuRadioGroup>
						</DropdownMenuContent>
					</DropdownMenu>
					<p v-if="form.errors.supplier_id" class="mt-1 text-xs text-destructive">{{ form.errors.supplier_id }}</p>
				</div>

				<div class="md:col-span-2">
					<Label class="mb-2">Mô tả</Label>
					<textarea v-model="form.description" rows="2" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm" />
					<p v-if="form.errors.description" class="mt-1 text-xs text-destructive">{{ form.errors.description }}</p>
				</div>

				<div class="md:col-span-2">
					<Label class="mb-2">Ghi chú</Label>
					<textarea v-model="form.note" rows="3" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm" />
					<p v-if="form.errors.note" class="mt-1 text-xs text-destructive">{{ form.errors.note }}</p>
				</div>

				<div class="md:col-span-2 flex items-center gap-2">
					<Button type="submit" :disabled="form.processing">
						<Save class="size-4" />
						{{ isEdit ? 'Lưu cập nhật' : 'Tạo chi phí doanh nghiệp' }}
					</Button>
					<Button as-child variant="outline">
						<Link :href="tenantRoutes.businessExpenses.index()">Hủy</Link>
					</Button>
				</div>
			</form>
		</div>
	</TenantLayout>
</template>
