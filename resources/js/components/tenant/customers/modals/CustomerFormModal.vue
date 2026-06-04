<script setup lang="ts">
import { ChevronDown, Save } from 'lucide-vue-next';
import { computed, reactive, watch } from 'vue';
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from '@/components/ui/dialog';
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuLabel,
	DropdownMenuRadioGroup,
	DropdownMenuRadioItem,
	DropdownMenuSeparator,
	DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { tenantRoutes } from '@/lib/tenantRoutes';

type CustomerOption = {
	id: number;
	code?: string | null;
	name: string;
	phone?: string | null;
	email?: string | null;
	type: string;
	is_active: boolean;
};

const props = defineProps<{
	open: boolean;
}>();

const emit = defineEmits<{
	'update:open': [value: boolean];
	saved: [customer: CustomerOption];
}>();

const form = reactive({
	code: '',
	name: '',
	phone: '',
	email: '',
	address: '',
	type: 'retail',
	tax_code: '',
	company_name: '',
	credit_limit: 0,
	default_due_days: 0,
	description: '',
	group_id: null as number | null,
	price_list_id: null as number | null,
	is_active: true,
});

const state = reactive({
	processing: false,
});

const errors = reactive<Record<string, string>>({});
const processing = computed(() => state.processing);

const resetForm = () => {
	form.code = '';
	form.name = '';
	form.phone = '';
	form.email = '';
	form.address = '';
	form.type = 'retail';
	form.tax_code = '';
	form.company_name = '';
	form.credit_limit = 0;
	form.default_due_days = 0;
	form.description = '';
	form.group_id = null;
	form.price_list_id = null;
	form.is_active = true;

	Object.keys(errors).forEach((key) => {
		delete errors[key];
	});
};

watch(
	() => props.open,
	(isOpen) => {
		if (!isOpen) return;
		resetForm();
	},
	{ immediate: true },
);

const typeMenuValue = computed({
	get: () => form.type,
	set: (value: string) => {
		form.type = value;
	},
});

const typeLabel = computed(() => {
	switch (form.type) {
		case 'wholesale':
			return 'Bán sỉ';
		case 'company':
			return 'Doanh nghiệp';
		default:
			return 'Bán lẻ';
	}
});

const csrfToken = () => {
	const metaToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
	if (metaToken !== '') {
		return metaToken;
	}

	const xsrfCookie = document.cookie
		.split('; ')
		.find((row) => row.startsWith('XSRF-TOKEN='));

	if (!xsrfCookie) {
		return '';
	}

	return decodeURIComponent(xsrfCookie.split('=')[1] ?? '');
};

const submit = async () => {
	if (form.name.trim() === '') {
		errors.name = 'Tên khách hàng là bắt buộc.';
		return;
	}

	state.processing = true;
	Object.keys(errors).forEach((key) => {
		delete errors[key];
	});

	const token = csrfToken();

	const payload = {
		_token: token || undefined,
		code: form.code.trim() === '' ? null : form.code.trim(),
		name: form.name.trim(),
		phone: form.phone.trim() === '' ? null : form.phone.trim(),
		email: form.email.trim() === '' ? null : form.email.trim(),
		address: form.address.trim() === '' ? null : form.address.trim(),
		type: form.type,
		tax_code: form.tax_code.trim() === '' ? null : form.tax_code.trim(),
		company_name: form.company_name.trim() === '' ? null : form.company_name.trim(),
		credit_limit: Number(form.credit_limit || 0),
		default_due_days: Number(form.default_due_days || 0),
		description: form.description.trim() === '' ? null : form.description.trim(),
		group_id: form.group_id,
		price_list_id: form.price_list_id,
		is_active: !!form.is_active,
	};

	try {
		const response = await fetch(tenantRoutes.customers.store(), {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				Accept: 'application/json',
				'Content-Type': 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
				...(token ? { 'X-CSRF-TOKEN': token } : {}),
			},
			body: JSON.stringify(payload),
		});

		const body = await response.json().catch(() => null);

		if (!response.ok) {
			const payloadErrors = body?.errors ?? {};
			if (typeof payloadErrors === 'object') {
				Object.entries(payloadErrors).forEach(([key, value]) => {
					const first = Array.isArray(value) ? value[0] : value;
					if (typeof first === 'string') {
						errors[key] = first;
					}
				});
			}

			if (!errors.name && typeof body?.message === 'string') {
				errors.general = body.message;
			}

			return;
		}

		const customer: CustomerOption | undefined = body?.customer;
		if (customer) {
			emit('saved', customer);
		}

		emit('update:open', false);
	} finally {
		state.processing = false;
	}
};
</script>

<template>
	<Dialog :open="open" @update:open="(value) => emit('update:open', value)">
		<DialogContent>
			<DialogHeader>
				<DialogTitle>Thêm khách hàng mới</DialogTitle>
				<DialogDescription>Tạo nhanh khách hàng ngay trong màn hình POS.</DialogDescription>
			</DialogHeader>

			<div class="space-y-4">
				<p v-if="errors.general" class="text-sm text-destructive">{{ errors.general }}</p>

				<div>
					<label class="mb-1 block text-sm font-medium">Tên khách hàng <span class="text-destructive">*</span></label>
					<input v-model="form.name" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring" placeholder="Nhập tên khách hàng" />
					<p v-if="errors.name" class="mt-1 text-xs text-destructive">{{ errors.name }}</p>
				</div>

				<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
					<div>
						<label class="mb-1 block text-sm font-medium">Số điện thoại</label>
						<input v-model="form.phone" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring" placeholder="VD: 090xxxxxxx" />
						<p v-if="errors.phone" class="mt-1 text-xs text-destructive">{{ errors.phone }}</p>
					</div>

					<div>
						<label class="mb-1 block text-sm font-medium">Email</label>
						<input v-model="form.email" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring" placeholder="VD: customer@email.com" />
						<p v-if="errors.email" class="mt-1 text-xs text-destructive">{{ errors.email }}</p>
					</div>
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium">Loại khách hàng</label>
					<DropdownMenu>
						<DropdownMenuTrigger as-child>
							<button type="button" class="inline-flex w-full items-center justify-between rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring">
								<span class="truncate">{{ typeLabel }}</span>
								<ChevronDown class="ml-2 size-4 text-muted-foreground" />
							</button>
						</DropdownMenuTrigger>
						<DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-56">
							<DropdownMenuLabel>Loại khách hàng</DropdownMenuLabel>
							<DropdownMenuSeparator />
							<DropdownMenuRadioGroup v-model="typeMenuValue">
								<DropdownMenuRadioItem value="retail">Bán lẻ</DropdownMenuRadioItem>
								<DropdownMenuRadioItem value="wholesale">Bán sỉ</DropdownMenuRadioItem>
								<DropdownMenuRadioItem value="company">Doanh nghiệp</DropdownMenuRadioItem>
							</DropdownMenuRadioGroup>
						</DropdownMenuContent>
					</DropdownMenu>
					<p v-if="errors.type" class="mt-1 text-xs text-destructive">{{ errors.type }}</p>
				</div>
			</div>

			<DialogFooter class="gap-2">
				<button type="button" class="inline-flex items-center gap-2 rounded-xl border border-border/70 bg-background px-4 py-2 text-sm transition hover:bg-muted/60" @click="emit('update:open', false)">
					Hủy
				</button>
				<button type="button" :disabled="processing" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-50" @click="submit">
					<Save class="size-4" />
					{{ processing ? 'Đang tạo...' : 'Tạo khách hàng' }}
				</button>
			</DialogFooter>
		</DialogContent>
	</Dialog>
</template>
