<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, Save } from 'lucide-vue-next';
import { computed } from 'vue';
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuLabel,
	DropdownMenuRadioGroup,
	DropdownMenuRadioItem,
	DropdownMenuSeparator,
	DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type UomPayload = {
	id: number;
	name: string;
	symbol?: string | null;
	base_uom_id?: number | null;
	is_active: boolean;
};

type BaseOption = {
	id: number;
	name: string;
	symbol?: string | null;
	label: string;
};

const props = defineProps<{
	mode: 'create' | 'edit';
	uom: UomPayload | null;
	baseOptions: BaseOption[];
}>();

const isEdit = computed(() => props.mode === 'edit');

const form = useForm({
	name: props.uom?.name ?? '',
	symbol: props.uom?.symbol ?? '',
	base_uom_id: props.uom?.base_uom_id ?? null,
	is_active: props.uom?.is_active ?? true,
});

const baseMenuValue = computed({
	get: () => (form.base_uom_id === null ? '' : String(form.base_uom_id)),
	set: (value: string) => {
		form.base_uom_id = value === '' ? null : Number(value);
	},
});

const baseLabel = computed(() => {
	if (form.base_uom_id === null) return 'Không chọn';
	return props.baseOptions.find((item) => item.id === form.base_uom_id)?.label ?? 'Không chọn';
});

const submit = () => {
	if (isEdit.value && props.uom) {
		form.put(tenantRoutes.uoms.update(props.uom.id));
		return;
	}

	form.post(tenantRoutes.uoms.store());
};
</script>

<template>
	<Head :title="isEdit ? 'Sửa đơn vị tính' : 'Thêm đơn vị tính'" />
	<TenantLayout>
		<div class="mx-auto max-w-3xl space-y-6">
			<div class="flex items-center gap-3">
				<Link :href="tenantRoutes.uoms.index()" class="rounded-xl border border-border/70 bg-background p-2 transition hover:bg-muted/60">
					<ArrowLeft class="size-5" />
				</Link>
				<div>
					<h1 class="text-2xl font-semibold tracking-tight">{{ isEdit ? 'Sửa đơn vị tính' : 'Thêm đơn vị tính mới' }}</h1>
					<p class="text-sm text-muted-foreground">Tên và ký hiệu sẽ được chuẩn hóa để tránh trùng lặp.</p>
				</div>
			</div>

			<form class="space-y-5 rounded-2xl border border-border/70 bg-card p-5" @submit.prevent="submit">
				<div>
					<label class="mb-1 block text-sm font-medium">Tên UOM <span class="text-destructive">*</span></label>
					<input v-model="form.name" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring" placeholder="VD: LON" />
					<p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium">Ký hiệu</label>
					<input v-model="form.symbol" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring" placeholder="VD: CAN" />
					<p v-if="form.errors.symbol" class="mt-1 text-xs text-destructive">{{ form.errors.symbol }}</p>
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium">Đơn vị gốc</label>
					<DropdownMenu>
						<DropdownMenuTrigger as-child>
							<button type="button" class="inline-flex w-full items-center justify-between rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring">
								<span class="truncate">{{ baseLabel }}</span>
								<ChevronDown class="ml-2 size-4 text-muted-foreground" />
							</button>
						</DropdownMenuTrigger>
						<DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-56">
							<DropdownMenuLabel>Đơn vị gốc</DropdownMenuLabel>
							<DropdownMenuSeparator />
							<DropdownMenuRadioGroup v-model="baseMenuValue">
								<DropdownMenuRadioItem value="">Không chọn</DropdownMenuRadioItem>
								<DropdownMenuRadioItem v-for="item in baseOptions" :key="item.id" :value="String(item.id)">
									{{ item.label }}
								</DropdownMenuRadioItem>
							</DropdownMenuRadioGroup>
						</DropdownMenuContent>
					</DropdownMenu>
					<p v-if="form.errors.base_uom_id" class="mt-1 text-xs text-destructive">{{ form.errors.base_uom_id }}</p>
				</div>

				<div>
					<label class="inline-flex items-center gap-2 text-sm">
						<input v-model="form.is_active" type="checkbox" class="rounded" />
						Đơn vị tính đang hoạt động
					</label>
					<p v-if="form.errors.is_active" class="mt-1 text-xs text-destructive">{{ form.errors.is_active }}</p>
				</div>

				<div class="flex items-center gap-2">
					<button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-50">
						<Save class="size-4" />
						{{ isEdit ? 'Lưu cập nhật' : 'Tạo đơn vị tính' }}
					</button>
					<Link :href="tenantRoutes.uoms.index()" class="rounded-xl border border-border/70 bg-background px-4 py-2.5 text-sm transition hover:bg-muted/60">Hủy</Link>
				</div>
			</form>
		</div>
	</TenantLayout>
</template>
