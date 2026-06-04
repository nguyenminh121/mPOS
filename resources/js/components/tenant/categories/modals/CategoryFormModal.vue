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

type CategoryOption = {
    id: number;
    code?: string | null;
    name: string;
    description?: string | null;
    parent_id?: number | null;
    is_active: boolean;
};

type CategoryTreeOption = CategoryOption & {
    treeLabel: string;
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    category: CategoryOption | null;
    categories: CategoryOption[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [category: CategoryOption];
}>();

const form = reactive({
    code: '',
    name: '',
    description: '',
    parent_id: '' as string,
    is_active: true,
});

const errors = reactive<Record<string, string>>({});
const processing = computed(() => state.processing);

const state = reactive({
    processing: false,
});

const resetForm = () => {
    form.code = '';
    form.name = '';
    form.description = '';
    form.parent_id = '';
    form.is_active = true;

    Object.keys(errors).forEach((key) => {
        delete errors[key];
    });
};

watch(
    () => [props.open, props.mode, props.category],
    () => {
        if (!props.open) return;

        resetForm();

        if (props.mode === 'edit' && props.category) {
            form.code = props.category.code ?? '';
            form.name = props.category.name ?? '';
            form.description = props.category.description ?? '';
            form.parent_id = props.category.parent_id ? String(props.category.parent_id) : '';
            form.is_active = !!props.category.is_active;
        }
    },
    { immediate: true },
);

const parentOptions = computed(() => {
    if (props.mode !== 'edit' || !props.category) {
        return props.categories;
    }

    return props.categories.filter((item) => item.id !== props.category?.id);
});

const parentTreeOptions = computed<CategoryTreeOption[]>(() => {
    const groupedByParent = new Map<number | null, CategoryOption[]>();

    for (const category of parentOptions.value) {
        const parentKey = category.parent_id ?? null;
        const siblings = groupedByParent.get(parentKey) ?? [];
        siblings.push(category);
        groupedByParent.set(parentKey, siblings);
    }

    for (const [parentId, siblings] of groupedByParent.entries()) {
        groupedByParent.set(parentId, [...siblings].sort((left, right) => left.name.localeCompare(right.name, 'vi')));
    }

    const flattened: CategoryTreeOption[] = [];

    const walk = (parentId: number | null, depth: number, visited: Set<number>) => {
        const nodes = groupedByParent.get(parentId) ?? [];

        for (const node of nodes) {
            if (visited.has(node.id)) {
                continue;
            }

            flattened.push({
                ...node,
                treeLabel: `${'— '.repeat(depth)}${node.name}`,
            });

            const nextVisited = new Set(visited);
            nextVisited.add(node.id);
            walk(node.id, depth + 1, nextVisited);
        }
    };

    walk(null, 0, new Set<number>());

    return flattened;
});

const parentMenuValue = computed({
    get: () => form.parent_id,
    set: (value: string) => {
        form.parent_id = value;
    },
});

const parentLabel = computed(() => {
    if (form.parent_id === '') return 'Không chọn';
    return parentTreeOptions.value.find((item) => String(item.id) === form.parent_id)?.treeLabel ?? 'Không chọn';
});

const title = computed(() => (props.mode === 'create' ? 'Thêm danh mục mới' : 'Sửa danh mục'));
const description = computed(() =>
    props.mode === 'create'
        ? 'Tạo nhanh danh mục để sử dụng ngay trong sản phẩm.'
        : 'Cập nhật thông tin danh mục đang chọn.',
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
        errors.name = 'Tên danh mục là bắt buộc.';
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
        description: form.description.trim() === '' ? null : form.description.trim(),
        parent_id: form.parent_id === '' ? null : Number(form.parent_id),
        is_active: !!form.is_active,
    };

    try {
        const endpoint = props.mode === 'create'
            ? tenantRoutes.categories.store()
            : tenantRoutes.categories.update(props.category!.id);

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

        const category: CategoryOption | undefined = body?.category;
        if (category) {
            emit('saved', category);
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
                    <label class="mb-1 block text-sm font-medium">Mã danh mục</label>
                    <input v-model="form.code" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring" placeholder="VD: CAT-ELEC" />
                    <p v-if="errors.code" class="mt-1 text-xs text-destructive">{{ errors.code }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Tên danh mục <span class="text-destructive">*</span></label>
                    <input v-model="form.name" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring" placeholder="Nhập tên danh mục" />
                    <p v-if="errors.name" class="mt-1 text-xs text-destructive">{{ errors.name }}</p>
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
                                <DropdownMenuRadioItem v-for="item in parentTreeOptions" :key="item.id" :value="String(item.id)">
                                    {{ item.treeLabel }}
                                </DropdownMenuRadioItem>
                            </DropdownMenuRadioGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <p v-if="errors.parent_id" class="mt-1 text-xs text-destructive">{{ errors.parent_id }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Mô tả</label>
                    <textarea v-model="form.description" rows="3" class="w-full resize-none rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring" placeholder="Mô tả danh mục (tùy chọn)" />
                    <p v-if="errors.description" class="mt-1 text-xs text-destructive">{{ errors.description }}</p>
                </div>

                <div>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input v-model="form.is_active" type="checkbox" class="rounded" />
                        Danh mục đang hoạt động
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
                    {{ mode === 'create' ? 'Tạo danh mục' : 'Lưu cập nhật' }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
