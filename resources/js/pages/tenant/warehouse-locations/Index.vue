<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, Eye, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
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
    code?: string | null;
    name: string;
    type: 'AREA' | 'FLOOR' | 'AISLE' | 'RACK' | 'SHELF' | 'BIN';
    is_active: boolean;
    parent?: { id: number; name: string; code?: string | null } | null;
    warehouse?: { id: number; name: string } | null;
    children_count?: number;
    product_storage_count?: number;
};

const props = defineProps<{
    locations: LocationRow[];
    warehouses: WarehouseRow[];
    filters?: {
        warehouse_id?: number | null;
    };
}>();

const typeLabelMap: Record<LocationRow['type'], string> = {
    AREA: 'Khu vực',
    FLOOR: 'Tầng',
    AISLE: 'Lối',
    RACK: 'Kệ',
    SHELF: 'Ngăn',
    BIN: 'Ô',
};

const selectedWarehouseFilter = computed(() => {
    const selectedId = Number(props.filters?.warehouse_id ?? 0);
    if (!selectedId) {
        return { label: 'Tất cả kho', value: '' };
    }

    const warehouse = props.warehouses.find((item) => item.id === selectedId);
    return {
        label: warehouse?.name ?? `Kho #${selectedId}`,
        value: String(selectedId),
    };
});

const onWarehouseFilterChange = (value: string) => {
    const parsed = Number(value || 0);

    router.get(tenantRoutes.warehouseLocations.index(), {
        warehouse_id: parsed > 0 ? parsed : undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const deleteLocation = (id: number) => {
    if (!confirm('Bạn có chắc muốn xóa vị trí kho này?')) return;
    router.delete(tenantRoutes.warehouseLocations.destroy(id));
};
</script>

<template>
    <Head title="Vị trí kho" />
    <TenantLayout>
        <div class="space-y-6">
            <section class="rounded-2xl border border-border/70 bg-card/95 p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">Vị trí kho</h1>
                        <p class="mt-1 text-sm text-muted-foreground">Quản lý cây vị trí theo khu vực / tầng / lối / kệ / ngăn / ô.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link :href="tenantRoutes.warehouses.index()" class="rounded-xl border border-border/70 bg-background px-4 py-2.5 text-sm transition hover:bg-muted/60">Kho hàng</Link>
                        <Link :href="tenantRoutes.warehouseLocations.create()" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                            <Plus class="size-4" />
                            Thêm vị trí
                        </Link>
                    </div>
                </div>

                <div class="mt-4 max-w-sm">
                    <label class="mb-1 block text-sm font-medium">Lọc theo kho</label>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button type="button" class="inline-flex w-full items-center justify-between rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring">
                                <span class="truncate">{{ selectedWarehouseFilter.label }}</span>
                                <ChevronDown class="ml-2 size-4 text-muted-foreground" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-56">
                            <DropdownMenuLabel>Kho hiển thị</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuRadioGroup :model-value="selectedWarehouseFilter.value" @update:model-value="(value) => onWarehouseFilterChange(String(value))">
                                <DropdownMenuRadioItem value="">Tất cả kho</DropdownMenuRadioItem>
                                <DropdownMenuRadioItem v-for="warehouse in warehouses" :key="warehouse.id" :value="String(warehouse.id)">
                                    {{ warehouse.name }}
                                </DropdownMenuRadioItem>
                            </DropdownMenuRadioGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-border/70 bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="p-3 font-medium">Kho</th>
                                <th class="p-3 font-medium">Mã</th>
                                <th class="p-3 font-medium">Tên vị trí</th>
                                <th class="p-3 font-medium">Loại</th>
                                <th class="p-3 font-medium">Vị trí cha</th>
                                <th class="p-3 text-center font-medium">Con</th>
                                <th class="p-3 text-center font-medium">Gán biến thể</th>
                                <th class="p-3 text-center font-medium">Trạng thái</th>
                                <th class="p-3 text-center font-medium">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="locations.length === 0">
                                <td colspan="9" class="p-8 text-center text-muted-foreground">Chưa có vị trí kho nào.</td>
                            </tr>
                            <tr v-for="row in locations" :key="row.id" class="border-t hover:bg-muted/20">
                                <td class="p-3">{{ row.warehouse?.name || `#${row.warehouse_id}` }}</td>
                                <td class="p-3 font-mono text-xs">{{ row.code || '-' }}</td>
                                <td class="p-3 font-medium">{{ row.name }}</td>
                                <td class="p-3">
                                    <span class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                                        {{ typeLabelMap[row.type] || row.type }}
                                    </span>
                                </td>
                                <td class="p-3 text-muted-foreground">{{ row.parent ? `${row.parent.code || '---'} - ${row.parent.name}` : '-' }}</td>
                                <td class="p-3 text-center">{{ row.children_count ?? 0 }}</td>
                                <td class="p-3 text-center">{{ row.product_storage_count ?? 0 }}</td>
                                <td class="p-3 text-center">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="row.is_active ? 'bg-primary/15 text-primary' : 'bg-muted text-muted-foreground'">
                                        {{ row.is_active ? 'Hoạt động' : 'Tắt' }}
                                    </span>
                                </td>
                                <td class="p-3">
                                    <div class="flex items-center justify-center">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <button type="button" class="inline-flex items-center justify-center rounded-md border border-border/70 p-1.5 hover:bg-muted/60" title="Thao tác">
                                                    <MoreHorizontal class="size-3.5" />
                                                </button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-44">
                                                <DropdownMenuItem as-child>
                                                    <Link :href="tenantRoutes.warehouseLocations.show(row.id)" class="inline-flex w-full items-center gap-2">
                                                        <Eye class="size-4" />
                                                        Xem chi tiết
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem as-child>
                                                    <Link :href="tenantRoutes.warehouseLocations.edit(row.id)" class="inline-flex w-full items-center gap-2">
                                                        <Pencil class="size-4" />
                                                        Sửa
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem class="text-destructive focus:text-destructive" @click="deleteLocation(row.id)">
                                                    <Trash2 class="size-4" />
                                                    Xóa
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </TenantLayout>
</template>
