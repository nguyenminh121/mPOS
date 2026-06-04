<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type WarehouseRow = {
    id: number;
    code?: string | null;
    name: string;
    address?: string | null;
    phone?: string | null;
    is_active: boolean;
    is_default: boolean;
    stocks_count?: number;
    import_receipts_count?: number;
};

const props = defineProps<{
    warehouses: WarehouseRow[];
}>();

const activeCount = computed(() => props.warehouses.filter((item) => item.is_active).length);

const deleteWarehouse = (id: number) => {
    if (!confirm('Bạn có chắc muốn xóa kho này?')) return;
    router.delete(tenantRoutes.warehouses.destroy(id));
};
</script>

<template>
    <Head title="Kho hàng" />
    <TenantLayout>
        <div class="space-y-6">
            <section class="rounded-2xl border border-border/70 bg-card/95 p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">Kho hàng</h1>
                        <p class="mt-1 text-sm text-muted-foreground">Quản lý thông tin kho và trạng thái hoạt động.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link :href="tenantRoutes.warehouseLocations.index()" class="rounded-xl border border-border/70 bg-background px-4 py-2.5 text-sm transition hover:bg-muted/60">
                            Vị trí kho
                        </Link>
                        <Link :href="tenantRoutes.warehouses.create()" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                            <Plus class="size-4" />
                            Thêm kho
                        </Link>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Tổng kho</p>
                        <p class="mt-1 text-2xl font-semibold">{{ warehouses.length }}</p>
                    </div>
                    <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Đang hoạt động</p>
                        <p class="mt-1 text-2xl font-semibold">{{ activeCount }}</p>
                    </div>
                    <div class="rounded-xl border border-border/70 bg-background/60 p-4">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Không hoạt động</p>
                        <p class="mt-1 text-2xl font-semibold">{{ warehouses.length - activeCount }}</p>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-border/70 bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="p-3 font-medium">Mã</th>
                                <th class="p-3 font-medium">Tên kho</th>
                                <th class="p-3 font-medium">Địa chỉ</th>
                                <th class="p-3 font-medium">Điện thoại</th>
                                <th class="p-3 text-center font-medium">Mặc định</th>
                                <th class="p-3 text-center font-medium">Trạng thái</th>
                                <th class="p-3 text-center font-medium">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="warehouses.length === 0">
                                <td colspan="7" class="p-8 text-center text-muted-foreground">Chưa có kho nào.</td>
                            </tr>
                            <tr v-for="row in warehouses" :key="row.id" class="border-t hover:bg-muted/20">
                                <td class="p-3 font-mono text-xs">{{ row.code || '-' }}</td>
                                <td class="p-3 font-medium">{{ row.name }}</td>
                                <td class="p-3 text-muted-foreground">{{ row.address || '-' }}</td>
                                <td class="p-3 text-muted-foreground">{{ row.phone || '-' }}</td>
                                <td class="p-3 text-center">
                                    <span v-if="row.is_default" class="rounded-full bg-primary/15 px-2.5 py-1 text-xs font-semibold text-primary">Mặc định</span>
                                    <span v-else class="text-muted-foreground">-</span>
                                </td>
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
                                                    <Link :href="tenantRoutes.warehouses.show(row.id)" class="inline-flex w-full items-center gap-2">
                                                        <Eye class="size-4" />
                                                        Xem chi tiết
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem as-child>
                                                    <Link :href="tenantRoutes.warehouses.edit(row.id)" class="inline-flex w-full items-center gap-2">
                                                        <Pencil class="size-4" />
                                                        Sửa
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem class="text-destructive focus:text-destructive" @click="deleteWarehouse(row.id)">
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
