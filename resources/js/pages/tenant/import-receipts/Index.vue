<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type ReceiptRow = {
    id: number;
    code: string;
    status: string;
    import_date?: string | null;
    total_amount?: number | string | null;
    paid_amount?: number | string | null;
    debt_amount?: number | string | null;
    supplier?: { id: number; name: string } | null;
    warehouse?: { id: number; name: string } | null;
    creator?: { id: number; name: string } | null;
    items_count?: number;
};

const props = defineProps<{
    receipts: ReceiptRow[];
}>();

const formatMoney = (value: number | string | null | undefined) => {
    const amount = Number(value || 0);
    return new Intl.NumberFormat('vi-VN').format(amount);
};

const deleteReceipt = (id: number) => {
    if (!confirm('Bạn có chắc muốn xóa phiếu nhập này?')) return;
    router.delete(tenantRoutes.importReceipts.destroy(id));
};

const canEditReceipt = (status: string): boolean => {
    const normalized = String(status).toUpperCase();
    return normalized !== 'CANCELLED';
};

const statusLabelMap: Record<string, string> = {
    DRAFT: 'Nháp',
    PENDING: 'Chờ duyệt',
    APPROVED: 'Đã duyệt',
    COMPLETED: 'Hoàn tất',
    CANCELLED: 'Đã hủy',
};

const statusLabel = (status: string): string => {
    const normalized = String(status).toUpperCase();
    return statusLabelMap[normalized] ?? normalized;
};
</script>

<template>
    <Head title="Phiếu nhập kho" />
    <TenantLayout>
        <div class="space-y-6">
            <section class="rounded-2xl border border-border/70 bg-card/95 p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">Phiếu nhập kho</h1>
                        <p class="mt-1 text-sm text-muted-foreground">Quản lý chứng từ nhập hàng và cập nhật tồn kho theo lô.</p>
                    </div>
                    <Link :href="tenantRoutes.importReceipts.create()" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                        <Plus class="size-4" />
                        Tạo phiếu nhập
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
                                <th class="p-3 font-medium">Nhà cung cấp</th>
                                <th class="p-3 text-center font-medium">Số sản phẩm</th>
                                <th class="p-3 text-right font-medium">Tổng tiền</th>
                                <th class="p-3 text-right font-medium">Đã thanh toán</th>
                                <th class="p-3 text-right font-medium">Công nợ</th>
                                <th class="p-3 text-center font-medium">Trạng thái</th>
                                <th class="p-3 text-center font-medium">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="receipts.length === 0">
                                <td colspan="9" class="p-8 text-center text-muted-foreground">Chưa có phiếu nhập nào.</td>
                            </tr>
                            <tr v-for="row in receipts" :key="row.id" class="border-t hover:bg-muted/20">
                                <td class="p-3 font-medium">{{ row.code }}</td>
                                <td class="p-3">{{ row.warehouse?.name || '-' }}</td>
                                <td class="p-3">{{ row.supplier?.name || '-' }}</td>
                                <td class="p-3 text-center">{{ row.items_count ?? 0 }}</td>
                                <td class="p-3 text-right font-semibold">{{ formatMoney(row.total_amount) }}</td>
                                <td class="p-3 text-right">{{ formatMoney(row.paid_amount) }}</td>
                                <td class="p-3 text-right" :class="Number(row.debt_amount || 0) > 0 ? 'font-semibold text-red-600' : 'text-muted-foreground'">
                                    {{ formatMoney(row.debt_amount) }}
                                </td>
                                <td class="p-3 text-center">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="row.status === 'COMPLETED' ? 'bg-primary/15 text-primary' : 'bg-muted text-muted-foreground'">
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
                                            <DropdownMenuContent align="end" class="w-44">
                                                <DropdownMenuItem as-child>
                                                    <Link :href="tenantRoutes.importReceipts.show(row.id)" class="inline-flex w-full items-center gap-2">
                                                        <Eye class="size-4" />
                                                        Xem chi tiết
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem v-if="canEditReceipt(row.status)" as-child>
                                                    <Link :href="tenantRoutes.importReceipts.edit(row.id)" class="inline-flex w-full items-center gap-2">
                                                        <Pencil class="size-4" />
                                                        Sửa
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem class="text-destructive focus:text-destructive" @click="deleteReceipt(row.id)">
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
