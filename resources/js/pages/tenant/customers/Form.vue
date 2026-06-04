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

type CustomerPayload = {
	id: number;
	code?: string | null;
	customer_ref?: string | number | null;
	name: string;
	email?: string | null;
	phone?: string | null;
	address?: string | null;
	type: string;
	tax_code?: string | null;
	company_name?: string | null;
	credit_limit: number;
	default_due_days: number;
	description?: string | null;
	group_id?: number | null;
	price_list_id?: number | null;
	is_active: boolean;
};

type Option = {
	id: number;
	code?: string | null;
	name: string;
	is_default?: boolean;
};

const props = defineProps<{
	mode: 'create' | 'edit';
	customer: CustomerPayload | null;
	customerGroups: Option[];
	priceLists: Option[];
}>();

const isEdit = computed(() => props.mode === 'edit');

const form = useForm({
	code: props.customer?.code ?? '',
	name: props.customer?.name ?? '',
	email: props.customer?.email ?? '',
	phone: props.customer?.phone ?? '',
	address: props.customer?.address ?? '',
	type: props.customer?.type ?? 'retail',
	tax_code: props.customer?.tax_code ?? '',
	company_name: props.customer?.company_name ?? '',
	credit_limit: props.customer?.credit_limit ?? 0,
	default_due_days: props.customer?.default_due_days ?? 0,
	description: props.customer?.description ?? '',
	group_id: props.customer?.group_id ?? null,
	price_list_id: props.customer?.price_list_id ?? null,
	is_active: props.customer?.is_active ?? true,
});

const submit = () => {
	if (isEdit.value && props.customer) {
		const customerRef = props.customer.customer_ref ?? props.customer.code ?? props.customer.id;
		form.put(tenantRoutes.customers.update(customerRef));
		return;
	}

	form.post(tenantRoutes.customers.store());
};

const customerTypeValue = computed({
	get: () => form.type,
	set: (value: string) => {
		form.type = value;
	},
});

const customerTypeLabel = computed(() => {
	switch (form.type) {
		case 'wholesale':
			return 'Bán sỉ';
		case 'company':
			return 'Doanh nghiệp';
		default:
			return 'Bán lẻ';
	}
});

const groupMenuValue = computed({
	get: () => (form.group_id === null ? '' : String(form.group_id)),
	set: (value: string) => {
		form.group_id = value === '' ? null : Number(value);
	},
});

const groupLabel = computed(() => {
	if (form.group_id === null) return 'Không chọn';
	const selected = props.customerGroups.find((item) => item.id === form.group_id);
	if (!selected) return 'Không chọn';

	return selected.code ? `${selected.name} (${selected.code})` : selected.name;
});

const priceListMenuValue = computed({
	get: () => (form.price_list_id === null ? '' : String(form.price_list_id)),
	set: (value: string) => {
		form.price_list_id = value === '' ? null : Number(value);
	},
});

const priceListLabel = computed(() => {
	if (form.price_list_id === null) return 'Không chọn';
	const selected = props.priceLists.find((item) => item.id === form.price_list_id);
	if (!selected) return 'Không chọn';

	return `${selected.name}${selected.is_default ? ' (Mặc định)' : ''}`;
});
</script>

<template>
	<Head :title="isEdit ? 'Sửa khách hàng' : 'Thêm khách hàng'" />
	<TenantLayout>
		<div class="mx-auto max-w-4xl space-y-6">
			<div class="flex items-center gap-3">
				<Button as-child variant="outline" size="icon">
					<Link :href="tenantRoutes.customers.index()">
						<ArrowLeft class="size-5" />
					</Link>
				</Button>
				<div>
					<h1 class="text-2xl font-semibold tracking-tight">{{ isEdit ? 'Sửa khách hàng' : 'Thêm khách hàng' }}</h1>
					<p class="text-sm text-muted-foreground">Thiết lập thông tin khách hàng, nhóm khách hàng và bảng giá áp dụng.</p>
				</div>
			</div>

			<form class="grid grid-cols-1 gap-4 rounded-2xl border border-border/70 bg-card p-5 md:grid-cols-2" @submit.prevent="submit">
				<div>
					<Label class="mb-2">Mã KH</Label>
					<Input v-model="form.code" placeholder="Tự động tạo" />
					<p v-if="form.errors.code" class="mt-1 text-xs text-destructive">{{ form.errors.code }}</p>
				</div>

				<div>
					<Label class="mb-2">Tên khách hàng *</Label>
					<Input v-model="form.name" placeholder="Nhập tên khách hàng" />
					<p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
				</div>

				<div>
					<Label class="mb-2">Loại khách hàng *</Label>
					<DropdownMenu>
						<DropdownMenuTrigger as-child>
							<Button type="button" variant="outline" class="w-full justify-between">
								{{ customerTypeLabel }}
								<ChevronDown class="size-4 text-muted-foreground" />
							</Button>
						</DropdownMenuTrigger>
						<DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-52">
							<DropdownMenuLabel>Loại khách hàng</DropdownMenuLabel>
							<DropdownMenuSeparator />
							<DropdownMenuRadioGroup v-model="customerTypeValue">
								<DropdownMenuRadioItem value="retail">Bán lẻ</DropdownMenuRadioItem>
								<DropdownMenuRadioItem value="wholesale">Bán sỉ</DropdownMenuRadioItem>
								<DropdownMenuRadioItem value="company">Doanh nghiệp</DropdownMenuRadioItem>
							</DropdownMenuRadioGroup>
						</DropdownMenuContent>
					</DropdownMenu>
					<p v-if="form.errors.type" class="mt-1 text-xs text-destructive">{{ form.errors.type }}</p>
				</div>

				<div>
					<Label class="mb-2">Số điện thoại</Label>
					<Input v-model="form.phone" />
					<p v-if="form.errors.phone" class="mt-1 text-xs text-destructive">{{ form.errors.phone }}</p>
				</div>

				<div>
					<Label class="mb-2">Email</Label>
					<Input v-model="form.email" />
					<p v-if="form.errors.email" class="mt-1 text-xs text-destructive">{{ form.errors.email }}</p>
				</div>

				<div>
					<Label class="mb-2">Công ty</Label>
					<Input v-model="form.company_name" />
				</div>

				<div>
					<Label class="mb-2">Mã số thuế</Label>
					<Input v-model="form.tax_code" />
				</div>

				<div>
					<Label class="mb-2">Nhóm khách hàng</Label>
					<DropdownMenu>
						<DropdownMenuTrigger as-child>
							<Button type="button" variant="outline" class="w-full justify-between">
								<span class="truncate text-left">{{ groupLabel }}</span>
								<ChevronDown class="size-4 text-muted-foreground" />
							</Button>
						</DropdownMenuTrigger>
						<DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-56">
							<DropdownMenuLabel>Nhóm khách hàng</DropdownMenuLabel>
							<DropdownMenuSeparator />
							<DropdownMenuRadioGroup v-model="groupMenuValue">
								<DropdownMenuRadioItem value="">Không chọn</DropdownMenuRadioItem>
								<DropdownMenuRadioItem v-for="group in customerGroups" :key="group.id" :value="String(group.id)">
									{{ group.name }} {{ group.code ? `(${group.code})` : '' }}
								</DropdownMenuRadioItem>
							</DropdownMenuRadioGroup>
						</DropdownMenuContent>
					</DropdownMenu>
					<p v-if="form.errors.group_id" class="mt-1 text-xs text-destructive">{{ form.errors.group_id }}</p>
				</div>

				<div>
					<Label class="mb-2">Bảng giá</Label>
					<DropdownMenu>
						<DropdownMenuTrigger as-child>
							<Button type="button" variant="outline" class="w-full justify-between">
								<span class="truncate text-left">{{ priceListLabel }}</span>
								<ChevronDown class="size-4 text-muted-foreground" />
							</Button>
						</DropdownMenuTrigger>
						<DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-56">
							<DropdownMenuLabel>Bảng giá áp dụng</DropdownMenuLabel>
							<DropdownMenuSeparator />
							<DropdownMenuRadioGroup v-model="priceListMenuValue">
								<DropdownMenuRadioItem value="">Không chọn</DropdownMenuRadioItem>
								<DropdownMenuRadioItem v-for="priceList in priceLists" :key="priceList.id" :value="String(priceList.id)">
									{{ priceList.name }} {{ priceList.is_default ? '(Mặc định)' : '' }}
								</DropdownMenuRadioItem>
							</DropdownMenuRadioGroup>
						</DropdownMenuContent>
					</DropdownMenu>
					<p v-if="form.errors.price_list_id" class="mt-1 text-xs text-destructive">{{ form.errors.price_list_id }}</p>
				</div>

				<div>
					<Label class="mb-2">Hạn mức công nợ</Label>
					<Input v-model.number="form.credit_limit" type="number" min="0" />
				</div>

				<div>
					<Label class="mb-2">Số ngày công nợ mặc định</Label>
					<Input v-model.number="form.default_due_days" type="number" min="0" />
				</div>

				<div class="md:col-span-2">
					<Label class="mb-2">Địa chỉ</Label>
					<textarea v-model="form.address" rows="2" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm" />
				</div>

				<div class="md:col-span-2">
					<Label class="mb-2">Mô tả</Label>
					<textarea v-model="form.description" rows="3" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm" />
				</div>

				<div class="md:col-span-2">
					<Label class="inline-flex items-center gap-2 text-sm">
						<input v-model="form.is_active" type="checkbox" class="rounded" />
						Khách hàng đang hoạt động
					</Label>
				</div>

				<div class="md:col-span-2 flex items-center gap-2">
					<Button type="submit" :disabled="form.processing">
						<Save class="size-4" />
						{{ isEdit ? 'Lưu cập nhật' : 'Tạo khách hàng' }}
					</Button>
					<Button as-child variant="outline">
						<Link :href="tenantRoutes.customers.index()">Hủy</Link>
					</Button>
				</div>
			</form>
		</div>
	</TenantLayout>
</template>
