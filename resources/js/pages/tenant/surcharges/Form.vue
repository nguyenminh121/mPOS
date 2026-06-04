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

type SurchargePayload = {
	id: number;
	code: string;
	name: string;
	description?: string | null;
	type: 'PERCENT' | 'FIXED_AMOUNT';
	value: number;
	is_auto_apply: boolean;
	is_refundable: boolean;
	is_active: boolean;
	category?: string | null;
};

const props = defineProps<{
	mode: 'create' | 'edit';
	surcharge: SurchargePayload | null;
}>();

const isEdit = computed(() => props.mode === 'edit');

const form = useForm({
	code: props.surcharge?.code ?? '',
	name: props.surcharge?.name ?? '',
	description: props.surcharge?.description ?? '',
	type: props.surcharge?.type ?? 'FIXED_AMOUNT',
	value: props.surcharge?.value ?? 0,
	is_auto_apply: props.surcharge?.is_auto_apply ?? false,
	is_refundable: props.surcharge?.is_refundable ?? true,
	is_active: props.surcharge?.is_active ?? true,
	category: props.surcharge?.category ?? '',
});

const typeMenuValue = computed({
	get: () => form.type,
	set: (value: string) => {
		form.type = value as 'PERCENT' | 'FIXED_AMOUNT';
	},
});

const typeLabel = computed(() => (form.type === 'PERCENT' ? 'Phần trăm (%)' : 'Số tiền cố định'));

const submit = () => {
	if (isEdit.value && props.surcharge) {
		form.put(tenantRoutes.surcharges.update(props.surcharge.id));
		return;
	}

	form.post(tenantRoutes.surcharges.store());
};
</script>

<template>
	<Head :title="isEdit ? 'Sửa chi phí phát sinh' : 'Thêm chi phí phát sinh'" />
	<TenantLayout>
		<div class="mx-auto max-w-4xl space-y-6">
			<div class="flex items-center gap-3">
				<Button as-child variant="outline" size="icon">
					<Link :href="tenantRoutes.surcharges.index()"><ArrowLeft class="size-5" /></Link>
				</Button>
				<div>
					<h1 class="text-2xl font-semibold tracking-tight">{{ isEdit ? 'Sửa chi phí phát sinh' : 'Thêm chi phí phát sinh' }}</h1>
					<p class="text-sm text-muted-foreground">Thiết lập phụ thu/chiết khấu phát sinh để áp dụng vào đơn hàng.</p>
				</div>
			</div>

			<form class="grid grid-cols-1 gap-4 rounded-2xl border border-border/70 bg-card p-5 md:grid-cols-2" @submit.prevent="submit">
				<div>
					<Label class="mb-2">Mã *</Label>
					<Input v-model="form.code" placeholder="VD: SUR-SHIP" />
					<p v-if="form.errors.code" class="mt-1 text-xs text-destructive">{{ form.errors.code }}</p>
				</div>

				<div>
					<Label class="mb-2">Tên chi phí *</Label>
					<Input v-model="form.name" placeholder="VD: Phí giao hàng" />
					<p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
				</div>

				<div>
					<Label class="mb-2">Loại tính *</Label>
					<DropdownMenu>
						<DropdownMenuTrigger as-child>
							<Button type="button" variant="outline" class="w-full justify-between">
								{{ typeLabel }}
								<ChevronDown class="size-4 text-muted-foreground" />
							</Button>
						</DropdownMenuTrigger>
						<DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-52">
							<DropdownMenuLabel>Loại tính giá trị</DropdownMenuLabel>
							<DropdownMenuSeparator />
							<DropdownMenuRadioGroup v-model="typeMenuValue">
								<DropdownMenuRadioItem value="FIXED_AMOUNT">Số tiền cố định</DropdownMenuRadioItem>
								<DropdownMenuRadioItem value="PERCENT">Phần trăm (%)</DropdownMenuRadioItem>
							</DropdownMenuRadioGroup>
						</DropdownMenuContent>
					</DropdownMenu>
					<p v-if="form.errors.type" class="mt-1 text-xs text-destructive">{{ form.errors.type }}</p>
				</div>

				<div>
					<Label class="mb-2">Giá trị *</Label>
					<Input v-model.number="form.value" type="number" min="0" step="0.01" :placeholder="form.type === 'PERCENT' ? 'VD: 10' : 'VD: 30000'" />
					<p v-if="form.errors.value" class="mt-1 text-xs text-destructive">{{ form.errors.value }}</p>
				</div>

				<div>
					<Label class="mb-2">Phân loại</Label>
					<Input v-model="form.category" placeholder="VD: Vận chuyển, Dịch vụ" />
					<p v-if="form.errors.category" class="mt-1 text-xs text-destructive">{{ form.errors.category }}</p>
				</div>

				<div class="md:col-span-2">
					<Label class="mb-2">Mô tả</Label>
					<textarea v-model="form.description" rows="3" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm" />
					<p v-if="form.errors.description" class="mt-1 text-xs text-destructive">{{ form.errors.description }}</p>
				</div>

				<div class="md:col-span-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
					<Label class="inline-flex items-center gap-2 text-sm"><input v-model="form.is_auto_apply" type="checkbox" class="rounded" />Tự động áp dụng</Label>
					<Label class="inline-flex items-center gap-2 text-sm"><input v-model="form.is_refundable" type="checkbox" class="rounded" />Hoàn trả được</Label>
					<Label class="inline-flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded" />Đang hoạt động</Label>
				</div>

				<div class="md:col-span-2 flex items-center gap-2">
					<Button type="submit" :disabled="form.processing">
						<Save class="size-4" />
						{{ isEdit ? 'Lưu cập nhật' : 'Tạo chi phí phát sinh' }}
					</Button>
					<Button as-child variant="outline">
						<Link :href="tenantRoutes.surcharges.index()">Hủy</Link>
					</Button>
				</div>
			</form>
		</div>
	</TenantLayout>
</template>
