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

type PriceListOption = {
	id: number;
	code: string;
	name: string;
	is_default: boolean;
};

type GroupPayload = {
	id: number;
	code: string;
	name: string;
	description?: string | null;
	default_price_list_id?: number | null;
	is_active: boolean;
};

const props = defineProps<{
	mode: 'create' | 'edit';
	customerGroup: GroupPayload | null;
	priceLists: PriceListOption[];
}>();

const isEdit = computed(() => props.mode === 'edit');

const form = useForm({
	code: props.customerGroup?.code ?? '',
	name: props.customerGroup?.name ?? '',
	description: props.customerGroup?.description ?? '',
	default_price_list_id: props.customerGroup?.default_price_list_id ?? null,
	is_active: props.customerGroup?.is_active ?? true,
});

const submit = () => {
	if (isEdit.value && props.customerGroup) {
		form.put(tenantRoutes.customerGroups.update(props.customerGroup.id));
		return;
	}

	form.post(tenantRoutes.customerGroups.store());
};

const priceListMenuValue = computed({
	get: () => (form.default_price_list_id === null ? '' : String(form.default_price_list_id)),
	set: (value: string) => {
		form.default_price_list_id = value === '' ? null : Number(value);
	},
});

const priceListLabel = computed(() => {
	if (form.default_price_list_id === null) return 'Không chọn';

	const selected = props.priceLists.find((priceList) => priceList.id === form.default_price_list_id);
    if (!selected) return 'Không chọn';

	return `${selected.name}${selected.is_default ? ' (Mặc định hệ thống)' : ''}`;
});
</script>

<template>
	<Head :title="isEdit ? 'Sửa nhóm khách hàng' : 'Thêm nhóm khách hàng'" />
	<TenantLayout>
		<div class="mx-auto max-w-3xl space-y-6">
			<div class="flex items-center gap-3">
				<Button as-child variant="outline" size="icon">
					<Link :href="tenantRoutes.customerGroups.index()"><ArrowLeft class="size-5" /></Link>
				</Button>
				<div>
					<h1 class="text-2xl font-semibold tracking-tight">{{ isEdit ? 'Sửa nhóm khách hàng' : 'Thêm nhóm khách hàng' }}</h1>
					<p class="text-sm text-muted-foreground">Gán bảng giá mặc định cho nhóm để tự động áp dụng khi bán hàng.</p>
				</div>
			</div>

			<form class="space-y-4 rounded-2xl border border-border/70 bg-card p-5" @submit.prevent="submit">
				<div>
					<Label class="mb-2">Mã nhóm *</Label>
					<Input v-model="form.code" placeholder="VD: VIP" />
					<p v-if="form.errors.code" class="mt-1 text-xs text-destructive">{{ form.errors.code }}</p>
				</div>

				<div>
					<Label class="mb-2">Tên nhóm *</Label>
					<Input v-model="form.name" />
					<p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
				</div>

				<div>
					<Label class="mb-2">Bảng giá mặc định</Label>
					<DropdownMenu>
						<DropdownMenuTrigger as-child>
							<Button type="button" variant="outline" class="w-full justify-between">
								<span class="truncate text-left">{{ priceListLabel }}</span>
								<ChevronDown class="size-4 text-muted-foreground" />
							</Button>
						</DropdownMenuTrigger>
						<DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-56">
							<DropdownMenuLabel>Bảng giá mặc định</DropdownMenuLabel>
							<DropdownMenuSeparator />
							<DropdownMenuRadioGroup v-model="priceListMenuValue">
								<DropdownMenuRadioItem value="">Không chọn</DropdownMenuRadioItem>
								<DropdownMenuRadioItem v-for="priceList in priceLists" :key="priceList.id" :value="String(priceList.id)">
									{{ priceList.name }} {{ priceList.is_default ? '(Mặc định hệ thống)' : '' }}
								</DropdownMenuRadioItem>
							</DropdownMenuRadioGroup>
						</DropdownMenuContent>
					</DropdownMenu>
					<p v-if="form.errors.default_price_list_id" class="mt-1 text-xs text-destructive">{{ form.errors.default_price_list_id }}</p>
				</div>

				<div>
					<Label class="mb-2">Mô tả</Label>
					<textarea v-model="form.description" rows="3" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm" />
				</div>

				<div>
					<Label class="inline-flex items-center gap-2 text-sm">
						<input v-model="form.is_active" type="checkbox" class="rounded" />
						Nhóm đang hoạt động
					</Label>
				</div>

				<div class="flex items-center gap-2">
					<Button type="submit" :disabled="form.processing">
						<Save class="size-4" />
						{{ isEdit ? 'Lưu cập nhật' : 'Tạo nhóm' }}
					</Button>
					<Button as-child variant="outline">
						<Link :href="tenantRoutes.customerGroups.index()">Hủy</Link>
					</Button>
				</div>
			</form>
		</div>
	</TenantLayout>
</template>
