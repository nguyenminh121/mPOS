<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, MoreHorizontal, Pencil, Trash2 } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type LocationDetail = {
    id: number;
    warehouse_id: number;
    code?: string | null;
    name: string;
    type: 'AREA' | 'FLOOR' | 'AISLE' | 'RACK' | 'SHELF' | 'BIN';
    parent_id?: number | null;
    capacity?: number | null;
    description?: string | null;
    is_active: boolean;
    warehouse?: { id: number; name: string; code?: string | null } | null;
    parent?: { id: number; name: string; code?: string | null } | null;
    children?: Array<{ id: number; name: string; code?: string | null; type: string; is_active: boolean }>;
    children_count?: number;
    product_storage_count?: number;
    stocks_count?: number;
};

const props = defineProps<{ location: LocationDetail }>();

const typeLabelMap: Record<LocationDetail['type'], string> = {
    AREA: 'Khu vực',
    FLOOR: 'Tầng',
    AISLE: 'Lối',
    RACK: 'Kệ',
    SHELF: 'Ngăn',
    BIN: 'Ô',
};

const childTypeLabel = (type: string): string => {
    return typeLabelMap[type as LocationDetail['type']] ?? type;
};

const deleteLocation = () => {
    if (!confirm('Bạn có chắc muốn xóa vị trí kho này?')) return;
    router.delete(tenantRoutes.warehouseLocations.destroy(props.location.id));
};
</script>

<template>
    <Head title="Chi tiết vị trí kho" />
    <TenantLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Link :href="tenantRoutes.warehouseLocations.index()" class="rounded-xl border border-border/70 bg-background p-2 transition hover:bg-muted/60">
                        <ArrowLeft class="size-5" />
                    </Link>
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">{{ location.name }}</h1>
                        <p class="text-sm text-muted-foreground">{{ location.code || '-' }} · {{ typeLabelMap[location.type] || location.type }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button type="button" class="inline-flex items-center justify-center rounded-md border border-border/70 p-2 hover:bg-muted/60" title="Thao tác">
                                <MoreHorizontal class="size-4" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-44">
                            <DropdownMenuItem as-child>
                                <Link :href="tenantRoutes.warehouseLocations.edit(location.id)" class="inline-flex w-full items-center gap-2">
                                    <Pencil class="size-4" />
                                    Sửa
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem class="text-destructive focus:text-destructive" @click="deleteLocation">
                                <Trash2 class="size-4" />
                                Xóa
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <section class="rounded-2xl border border-border/70 bg-card p-5">
                <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Kho</dt>
                        <dd class="mt-1 text-sm font-medium">{{ location.warehouse?.name || `#${location.warehouse_id}` }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Vị trí cha</dt>
                        <dd class="mt-1 text-sm font-medium">{{ location.parent?.name || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Trạng thái</dt>
                        <dd class="mt-1 text-sm">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="location.is_active ? 'bg-primary/15 text-primary' : 'bg-muted text-muted-foreground'">
                                {{ location.is_active ? 'Hoạt động' : 'Tắt' }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Sức chứa</dt>
                        <dd class="mt-1 text-sm font-medium">{{ location.capacity ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Số vị trí con</dt>
                        <dd class="mt-1 text-sm font-medium">{{ location.children_count ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Số biến thể đã gán</dt>
                        <dd class="mt-1 text-sm font-medium">{{ location.product_storage_count ?? 0 }}</dd>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Mô tả</dt>
                        <dd class="mt-1 whitespace-pre-wrap text-sm text-muted-foreground">{{ location.description || '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-border/70 bg-card p-5">
                <h2 class="mb-3 text-sm font-semibold">Vị trí con</h2>
                <div v-if="!(location.children || []).length" class="text-sm text-muted-foreground">Không có vị trí con.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="p-2.5 font-medium">Mã</th>
                                <th class="p-2.5 font-medium">Tên</th>
                                <th class="p-2.5 font-medium">Loại</th>
                                <th class="p-2.5 text-center font-medium">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="child in location.children" :key="child.id" class="border-t">
                                <td class="p-2.5 font-mono text-xs">{{ child.code || '-' }}</td>
                                <td class="p-2.5">
                                    <Link :href="tenantRoutes.warehouseLocations.show(child.id)" class="font-medium text-primary hover:underline">{{ child.name }}</Link>
                                </td>
                                <td class="p-2.5">{{ childTypeLabel(child.type) }}</td>
                                <td class="p-2.5 text-center">
                                    <span class="rounded-full px-2 py-0.5 text-xs" :class="child.is_active ? 'bg-primary/15 text-primary' : 'bg-muted text-muted-foreground'">
                                        {{ child.is_active ? 'Hoạt động' : 'Tắt' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </TenantLayout>
</template>
