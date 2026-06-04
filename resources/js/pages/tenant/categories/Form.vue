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

type CategoryPayload = {
	id: number;
	code?: string | null;
	name: string;
	description?: string | null;
	parent_id?: number | null;
	is_active: boolean;
};

type ParentOption = {
	id: number;
	name: string;
	tree_label: string;
};

const props = defineProps<{
	mode: 'create' | 'edit';
	category: CategoryPayload | null;
	parentOptions: ParentOption[];
}>();

const isEdit = computed(() => props.mode === 'edit');

const form = useForm({
	code: props.category?.code ?? '',
	name: props.category?.name ?? '',
	description: props.category?.description ?? '',
	parent_id: props.category?.parent_id ?? null,
	is_active: props.category?.is_active ?? true,
});

const submit = () => {
	if (isEdit.value && props.category) {
		form.put(tenantRoutes.categories.update(props.category.id));
		return;
	}

	form.post(tenantRoutes.categories.store());
};

const parentMenuValue = computed({
	get: () => (form.parent_id === null ? '' : String(form.parent_id)),
	set: (value: string) => {
		form.parent_id = value === '' ? null : Number(value);
	},
});

const parentLabel = computed(() => {
	if (form.parent_id === null) return 'Không chọn';

	return props.parentOptions.find((item) => item.id === form.parent_id)?.tree_label ?? 'Không chọn';
});
</script>

<template>
	<Head :title="isEdit ? 'Sửa danh mục' : 'Thêm danh mục'" />
	<TenantLayout>
		<div class="mx-auto max-w-3xl space-y-6">
			<div class="flex items-center gap-3">
				<Link :href="tenantRoutes.categories.index()" class="rounded-xl border border-border/70 bg-background p-2 transition hover:bg-muted/60">
					<ArrowLeft class="size-5" />
				</Link>
				<div>
					<h1 class="text-2xl font-semibold tracking-tight">{{ isEdit ? 'Sửa danh mục' : 'Thêm danh mục mới' }}</h1>
					<p class="text-sm text-muted-foreground">Thiết lập thông tin danh mục và quan hệ cha/con.</p>
				</div>
			</div>

			<form class="space-y-5 rounded-2xl border border-border/70 bg-card p-5" @submit.prevent="submit">
				<div>
					<label class="mb-1 block text-sm font-medium">Mã danh mục</label>
					<input v-model="form.code" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring" placeholder="VD: CAT-ELEC" />
					<p v-if="form.errors.code" class="mt-1 text-xs text-destructive">{{ form.errors.code }}</p>
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium">Tên danh mục <span class="text-destructive">*</span></label>
					<input v-model="form.name" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring" placeholder="Nhập tên danh mục" />
					<p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium">Danh mục cha</label>
					<DropdownMenu>
						<DropdownMenuTrigger as-child>
							<button type="button" class="inline-flex w-full items-center justify-between rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring">
								<span class="truncate">{{ parentLabel }}</span>
								<ChevronDown class="ml-2 size-4 text-muted-foreground" />
							</button>
						</DropdownMenuTrigger>
						<DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-56">
							<DropdownMenuLabel>Danh mục cha</DropdownMenuLabel>
							<DropdownMenuSeparator />
							<DropdownMenuRadioGroup v-model="parentMenuValue">
								<DropdownMenuRadioItem value="">Không chọn</DropdownMenuRadioItem>
								<DropdownMenuRadioItem v-for="item in parentOptions" :key="item.id" :value="String(item.id)">
									{{ item.tree_label }}
								</DropdownMenuRadioItem>
							</DropdownMenuRadioGroup>
						</DropdownMenuContent>
					</DropdownMenu>
					<p v-if="form.errors.parent_id" class="mt-1 text-xs text-destructive">{{ form.errors.parent_id }}</p>
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium">Mô tả</label>
					<textarea v-model="form.description" rows="4" class="w-full resize-none rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring" placeholder="Mô tả danh mục (tùy chọn)" />
					<p v-if="form.errors.description" class="mt-1 text-xs text-destructive">{{ form.errors.description }}</p>
				</div>

				<div>
					<label class="inline-flex items-center gap-2 text-sm">
						<input v-model="form.is_active" type="checkbox" class="rounded" />
						Danh mục đang hoạt động
					</label>
					<p v-if="form.errors.is_active" class="mt-1 text-xs text-destructive">{{ form.errors.is_active }}</p>
				</div>

				<div class="flex items-center gap-2">
					<button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-50">
						<Save class="size-4" />
						{{ isEdit ? 'Lưu cập nhật' : 'Tạo danh mục' }}
					</button>
					<Link :href="tenantRoutes.categories.index()" class="rounded-xl border border-border/70 bg-background px-4 py-2.5 text-sm transition hover:bg-muted/60">Hủy</Link>
				</div>
			</form>
		</div>
	</TenantLayout>
</template>
