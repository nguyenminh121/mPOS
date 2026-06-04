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

type UomOption = {
    id: number;
    name: string;
    symbol?: string | null;
    base_uom_id?: number | null;
    is_active: boolean;
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    uom: UomOption | null;
    uoms: UomOption[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [uom: UomOption];
}>();

const form = reactive({
    name: '',
    symbol: '',
    base_uom_id: '' as string,
    is_active: true,
});

const state = reactive({
    processing: false,
});

const errors = reactive<Record<string, string>>({});
const processing = computed(() => state.processing);

const resetForm = () => {
    form.name = '';
    form.symbol = '';
    form.base_uom_id = '';
    form.is_active = true;

    Object.keys(errors).forEach((key) => {
        delete errors[key];
    });
};

watch(
    () => [props.open, props.mode, props.uom],
    () => {
        if (!props.open) return;

        resetForm();

        if (props.mode === 'edit' && props.uom) {
            form.name = props.uom.name ?? '';
            form.symbol = props.uom.symbol ?? '';
            form.base_uom_id = props.uom.base_uom_id ? String(props.uom.base_uom_id) : '';
            form.is_active = !!props.uom.is_active;
        }
    },
    { immediate: true },
);

const baseOptions = computed(() => {
    if (props.mode !== 'edit' || !props.uom) {
        return props.uoms;
    }

    return props.uoms.filter((item) => item.id !== props.uom?.id);
});

const baseMenuValue = computed({
    get: () => form.base_uom_id,
    set: (value: string) => {
        form.base_uom_id = value;
    },
});

const baseLabel = computed(() => {
    if (form.base_uom_id === '') return 'Không chọn';

    const target = baseOptions.value.find((item) => String(item.id) === form.base_uom_id);
    if (!target) return 'Không chọn';

    return target.symbol ? `${target.name} (${target.symbol})` : target.name;
});

const title = computed(() => (props.mode === 'create' ? 'Thêm đơn vị tính mới' : 'Sửa đơn vị tính'));
const description = computed(() =>
    props.mode === 'create'
        ? 'Tạo nhanh đơn vị tính để dùng ngay cho sản phẩm.'
        : 'Cập nhật thông tin đơn vị tính đang chọn.',
);

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
        errors.name = 'Tên đơn vị tính là bắt buộc.';
        return;
    }

    state.processing = true;
    Object.keys(errors).forEach((key) => {
        delete errors[key];
    });

    const token = csrfToken();

    const payload = {
        _token: token || undefined,
        name: form.name.trim(),
        symbol: form.symbol.trim() === '' ? null : form.symbol.trim(),
        base_uom_id: form.base_uom_id === '' ? null : Number(form.base_uom_id),
        is_active: !!form.is_active,
    };

    try {
        const endpoint = props.mode === 'create'
            ? tenantRoutes.uoms.store()
            : tenantRoutes.uoms.update(props.uom!.id);

        const response = await fetch(endpoint, {
            method: props.mode === 'create' ? 'POST' : 'PUT',
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

        const uom: UomOption | undefined = body?.uom;
        if (uom) {
            emit('saved', uom);
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
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <p v-if="errors.general" class="text-sm text-destructive">{{ errors.general }}</p>

                <div>
                    <label class="mb-1 block text-sm font-medium">Tên đơn vị tính <span class="text-destructive">*</span></label>
                    <input v-model="form.name" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring" placeholder="VD: LON" />
                    <p v-if="errors.name" class="mt-1 text-xs text-destructive">{{ errors.name }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Ký hiệu</label>
                    <input v-model="form.symbol" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring" placeholder="VD: CAN" />
                    <p v-if="errors.symbol" class="mt-1 text-xs text-destructive">{{ errors.symbol }}</p>
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
                                    {{ item.symbol ? `${item.name} (${item.symbol})` : item.name }}
                                </DropdownMenuRadioItem>
                            </DropdownMenuRadioGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <p v-if="errors.base_uom_id" class="mt-1 text-xs text-destructive">{{ errors.base_uom_id }}</p>
                </div>

                <div>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input v-model="form.is_active" type="checkbox" class="rounded" />
                        Đơn vị tính đang hoạt động
                    </label>
                    <p v-if="errors.is_active" class="mt-1 text-xs text-destructive">{{ errors.is_active }}</p>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-border/70 bg-background px-4 py-2 text-sm transition hover:bg-muted/60" @click="emit('update:open', false)">
                    Hủy
                </button>
                <button type="button" :disabled="processing" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-50" @click="submit">
                    <Save class="size-4" />
                    {{ mode === 'create' ? 'Tạo đơn vị tính' : 'Lưu cập nhật' }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
