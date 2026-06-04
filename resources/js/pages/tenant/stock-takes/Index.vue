<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, MoreHorizontal, Pencil, Plus, RotateCcw, Trash2, CheckCircle2 } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type StockTakeRow = {
    id: number;
    code: string;
    status: string;
    completed_at?: string | null;
    warehouse?: { id: number; name: string } | null;
    creator?: { id: number; name: string } | null;
    items_count?: number;
};

const props = defineProps<{
    stockTakes: StockTakeRow[];
}>();

const normalizeStatus = (status: string): 'DRAFT' | 'IN_PROGRESS' | 'COMPLETED' | 'CANCELLED' => {
    const normalized = String(status).toUpperCase();

    if (normalized === 'IN_PROGRESS') return 'IN_PROGRESS';
    if (normalized === 'COMPLETED') return 'COMPLETED';
    if (normalized === 'CANCELLED') return 'CANCELLED';

    return 'DRAFT';
};

const statusLabel = (status: string): string => {
    const normalized = normalizeStatus(status);

    if (normalized === 'IN_PROGRESS') return 'Đang kiểm kê';
    if (normalized === 'COMPLETED') return 'Đã hoàn tất';
    if (normalized === 'CANCELLED') return 'Đã hủy';

    return 'Nháp';
};

const statusBadgeClass = (status: string): string => {
    const normalized = normalizeStatus(status);

    if (normalized === 'IN_PROGRESS') return 'bg-amber-500/15 text-amber-700 dark:text-amber-300';
    if (normalized === 'COMPLETED') return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300';
    if (normalized === 'CANCELLED') return 'bg-destructive/15 text-destructive';

    return 'bg-muted text-muted-foreground';
};

const canEdit = (status: string): boolean => ['DRAFT', 'IN_PROGRESS'].includes(String(status).toUpperCase());
const canComplete = (status: string): boolean => String(status).toUpperCase() === 'IN_PROGRESS';
const canUndo = (status: string): boolean => String(status).toUpperCase() === 'COMPLETED';
const canDelete = (status: string): boolean => String(status).toUpperCase() !== 'COMPLETED';

const deleteStockTake = (id: number) => {
    if (!confirm('Bạn có chắc muốn xóa phiếu kiểm kê này?')) return;
    router.delete(tenantRoutes.stockTakes.destroy(id));
};

const completeStockTake = (id: number) => {
    if (!confirm('Xác nhận hoàn tất kiểm kê và cập nhật tồn kho?')) return;
    router.post(tenantRoutes.stockTakes.complete(id));
};

const undoStockTake = (id: number) => {
    if (!confirm('Xác nhận undo để hoàn tác điều chỉnh tồn kho?')) return;
    router.post(tenantRoutes.stockTakes.undo(id));
};
</script>

<template>
    <Head title="Phiếu kiểm kê" />
    <TenantLayout>
        <div class="space-y-6">
            <section class="rounded-2xl border border-border/70 bg-card/95 p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">Phiếu kiểm kê</h1>
                        <p class="mt-1 text-sm text-muted-foreground">Soát kho thực tế, cân bằng số liệu hệ thống và ghi nhận điều chỉnh tồn.</p>
                    </div>
                    <Link :href="tenantRoutes.stockTakes.create()" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                        <Plus class="size-4" />
                        Tạo phiếu kiểm kê
                    </Link>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-border/70 bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="p-3 font-medium">Mã phiếu</th>
                                <th class="p-3 font-medium">Kho</th>
                                <th class="p-3 font-medium">Người tạo</th>
                                <th class="p-3 text-center font-medium">Số sản phẩm</th>
                                <th class="p-3 text-center font-medium">Trạng thái</th>
                                <th class="p-3 text-center font-medium">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="stockTakes.length === 0">
                                <td colspan="6" class="p-8 text-center text-muted-foreground">Chưa có phiếu kiểm kê nào.</td>
                            </tr>
                            <tr v-for="row in stockTakes" :key="row.id" class="border-t hover:bg-muted/20">
                                <td class="p-3 font-medium">{{ row.code }}</td>
                                <td class="p-3">{{ row.warehouse?.name || '-' }}</td>
                                <td class="p-3">{{ row.creator?.name || '-' }}</td>
                                <td class="p-3 text-center">{{ row.items_count ?? 0 }}</td>
                                <td class="p-3 text-center">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadgeClass(row.status)">
                                        {{ statusLabel(row.status) }}
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
                                            <DropdownMenuContent align="end" class="w-52">
                                                <DropdownMenuItem as-child>
                                                    <Link :href="tenantRoutes.stockTakes.show(row.id)" class="inline-flex w-full items-center gap-2">
                                                        <Eye class="size-4" />
                                                        Xem chi tiết
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem v-if="canEdit(row.status)" as-child>
                                                    <Link :href="tenantRoutes.stockTakes.edit(row.id)" class="inline-flex w-full items-center gap-2">
                                                        <Pencil class="size-4" />
                                                        Sửa
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem v-if="canComplete(row.status)" @click="completeStockTake(row.id)">
                                                    <CheckCircle2 class="size-4" />
                                                    Hoàn tất kiểm kê
                                                </DropdownMenuItem>
                                                <DropdownMenuItem v-if="canUndo(row.status)" @click="undoStockTake(row.id)">
                                                    <RotateCcw class="size-4" />
                                                    Hoàn tác kiểm kê
                                                </DropdownMenuItem>
                                                <DropdownMenuItem v-if="canDelete(row.status)" class="text-destructive focus:text-destructive" @click="deleteStockTake(row.id)">
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
