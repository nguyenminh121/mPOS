<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, Save } from 'lucide-vue-next';
import { computed, watch } from 'vue';
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

type WarehouseRow = {
    id: number;
    name: string;
    code?: string | null;
    is_active?: boolean;
};

type LocationRow = {
    id: number;
    warehouse_id: number;
    parent_id?: number | null;
    code?: string | null;
    name: string;
    type: 'AREA' | 'FLOOR' | 'AISLE' | 'RACK' | 'SHELF' | 'BIN';
    capacity?: number | null;
    description?: string | null;
    is_active: boolean;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    location: LocationRow | null;
    warehouses: WarehouseRow[];
    locations: LocationRow[];
}>();

const isEdit = computed(() => props.mode === 'edit');
const typeOptions: Array<LocationRow['type']> = ['AREA', 'FLOOR', 'AISLE', 'RACK', 'SHELF', 'BIN'];

const typeLabelMap: Record<LocationRow['type'], string> = {
    AREA: 'Khu vực',
    FLOOR: 'Tầng',
    AISLE: 'Lối',
    RACK: 'Kệ',
    SHELF: 'Ngăn',
    BIN: 'Ô',
};

type WarehouseLocationFormState = {
    warehouse_id: number | null;
    code: string;
    name: string;
    type: LocationRow['type'];
    parent_id: number | null;
    capacity: number | null;
    description: string;
    is_active: boolean;
};

const form = useForm<WarehouseLocationFormState>({
    warehouse_id: props.location?.warehouse_id ?? props.warehouses[0]?.id ?? null,
    code: props.location?.code ?? '',
    name: props.location?.name ?? '',
    type: props.location?.type ?? 'AREA',
    parent_id: props.location?.parent_id ?? null,
    capacity: props.location?.capacity ?? null,
    description: props.location?.description ?? '',
    is_active: props.location?.is_active ?? true,
});

const warehouseMenuValue = computed({
    get: () => (form.warehouse_id === null ? '' : String(form.warehouse_id)),
    set: (value: string) => {
        form.warehouse_id = value === '' ? null : Number(value);
    },
});

const typeMenuValue = computed({
    get: () => form.type,
    set: (value: string) => {
        form.type = value as LocationRow['type'];
    },
});

const parentMenuValue = computed({
    get: () => (form.parent_id === null ? '' : String(form.parent_id)),
    set: (value: string) => {
        form.parent_id = value === '' ? null : Number(value);
    },
});

const selectedWarehouseLabel = computed(() => {
    if (form.warehouse_id === null) return 'Chọn kho';
    return props.warehouses.find((warehouse) => warehouse.id === form.warehouse_id)?.name ?? `Kho #${form.warehouse_id}`;
});

const selectedTypeLabel = computed(() => typeLabelMap[form.type] ?? form.type);

const selectedParentLabel = computed(() => {
    if (form.parent_id === null) return 'Không chọn';

    const parent = parentOptions.value.find((location) => location.id === form.parent_id);
    if (!parent) return `#${form.parent_id}`;

    return `${typeLabelMap[parent.type] ?? parent.type} · ${parent.code || '---'} - ${parent.name}`;
});

const parentOptions = computed(() => {
    if (form.warehouse_id === null) return [];

    return props.locations
        .filter((location) => Number(location.warehouse_id) === Number(form.warehouse_id))
        .filter((location) => !props.location || location.id !== props.location.id)
        .sort((left, right) => String(left.name).localeCompare(String(right.name), 'vi'));
});

watch(
    () => form.warehouse_id,
    () => {
        if (form.parent_id === null) return;

        const stillValid = parentOptions.value.some((location) => location.id === form.parent_id);
        if (!stillValid) {
            form.parent_id = null;
        }
    },
);

const submit = () => {
    if (isEdit.value && props.location) {
        form.put(tenantRoutes.warehouseLocations.update(props.location.id));
        return;
    }

    form.post(tenantRoutes.warehouseLocations.store());
};
</script>

<template>
    <Head :title="isEdit ? 'Sửa vị trí kho' : 'Thêm vị trí kho'" />
    <TenantLayout>
        <div class="mx-auto max-w-3xl space-y-6">
            <div class="flex items-center gap-3">
                <Link :href="tenantRoutes.warehouseLocations.index()" class="rounded-xl border border-border/70 bg-background p-2 transition hover:bg-muted/60">
                    <ArrowLeft class="size-5" />
                </Link>
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ isEdit ? 'Sửa vị trí kho' : 'Thêm vị trí kho mới' }}</h1>
                    <p class="text-sm text-muted-foreground">Quản lý vị trí theo cấu trúc tầng/kệ/khu vực trong kho.</p>
                </div>
            </div>

            <form class="space-y-5 rounded-2xl border border-border/70 bg-card p-5" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Kho <span class="text-destructive">*</span></label>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button type="button" class="inline-flex w-full items-center justify-between rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring">
                                    <span class="truncate">{{ selectedWarehouseLabel }}</span>
                                    <ChevronDown class="ml-2 size-4 text-muted-foreground" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-56">
                                <DropdownMenuLabel>Chọn kho</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuRadioGroup v-model="warehouseMenuValue">
                                    <DropdownMenuRadioItem value="">Chọn kho</DropdownMenuRadioItem>
                                    <DropdownMenuRadioItem v-for="warehouse in warehouses" :key="warehouse.id" :value="String(warehouse.id)">
                                        {{ warehouse.name }}
                                    </DropdownMenuRadioItem>
                                </DropdownMenuRadioGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <p v-if="form.errors.warehouse_id" class="mt-1 text-xs text-destructive">{{ form.errors.warehouse_id }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Loại vị trí <span class="text-destructive">*</span></label>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button type="button" class="inline-flex w-full items-center justify-between rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring">
                                    <span class="truncate">{{ selectedTypeLabel }}</span>
                                    <ChevronDown class="ml-2 size-4 text-muted-foreground" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-56">
                                <DropdownMenuLabel>Loại vị trí</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuRadioGroup v-model="typeMenuValue">
                                    <DropdownMenuRadioItem v-for="type in typeOptions" :key="type" :value="type">{{ typeLabelMap[type] }}</DropdownMenuRadioItem>
                                </DropdownMenuRadioGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <p v-if="form.errors.type" class="mt-1 text-xs text-destructive">{{ form.errors.type }}</p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Mã vị trí <span class="text-destructive">*</span></label>
                        <input v-model="form.code" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm" placeholder="VD: A1-F1-R01" />
                        <p v-if="form.errors.code" class="mt-1 text-xs text-destructive">{{ form.errors.code }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Tên vị trí <span class="text-destructive">*</span></label>
                        <input v-model="form.name" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm" placeholder="VD: Kệ 01 - Tầng 1" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Vị trí cha</label>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button type="button" class="inline-flex w-full items-center justify-between rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring">
                                    <span class="truncate">{{ selectedParentLabel }}</span>
                                    <ChevronDown class="ml-2 size-4 text-muted-foreground" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-72">
                                <DropdownMenuLabel>Vị trí cha</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuRadioGroup v-model="parentMenuValue">
                                    <DropdownMenuRadioItem value="">Không chọn</DropdownMenuRadioItem>
                                    <DropdownMenuRadioItem v-for="location in parentOptions" :key="location.id" :value="String(location.id)">
                                        {{ (typeLabelMap[location.type] ?? location.type) + ' · ' + (location.code || '---') + ' - ' + location.name }}
                                    </DropdownMenuRadioItem>
                                </DropdownMenuRadioGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <p v-if="form.errors.parent_id" class="mt-1 text-xs text-destructive">{{ form.errors.parent_id }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Sức chứa</label>
                        <input v-model.number="form.capacity" type="number" min="0" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm" placeholder="Tùy chọn" />
                        <p v-if="form.errors.capacity" class="mt-1 text-xs text-destructive">{{ form.errors.capacity }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Mô tả</label>
                    <textarea v-model="form.description" rows="3" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm" placeholder="Mô tả vị trí kho..." />
                    <p v-if="form.errors.description" class="mt-1 text-xs text-destructive">{{ form.errors.description }}</p>
                </div>

                <label class="inline-flex items-center gap-2 text-sm">
                    <input v-model="form.is_active" type="checkbox" class="rounded" />
                    Vị trí đang hoạt động
                </label>

                <div class="flex items-center gap-2">
                    <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-50">
                        <Save class="size-4" />
                        {{ isEdit ? 'Lưu cập nhật' : 'Tạo vị trí' }}
                    </button>
                    <Link :href="tenantRoutes.warehouseLocations.index()" class="rounded-xl border border-border/70 bg-background px-4 py-2.5 text-sm transition hover:bg-muted/60">Hủy</Link>
                </div>
            </form>
        </div>
    </TenantLayout>
</template>
