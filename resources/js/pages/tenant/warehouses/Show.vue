<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Pencil } from 'lucide-vue-next';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type WarehouseDetail = {
    id: number;
    code?: string | null;
    name: string;
    address?: string | null;
    phone?: string | null;
    is_active: boolean;
    is_default: boolean;
    stocks_count?: number;
    import_receipts_count?: number;
    stock_movements_count?: number;
};

const { warehouse } = defineProps<{
    warehouse: WarehouseDetail;
}>();
</script>

<template>
    <Head title="Chi tiết kho" />
    <TenantLayout>
        <div class="mx-auto max-w-3xl space-y-6">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Link :href="tenantRoutes.warehouses.index()" class="rounded-xl border border-border/70 bg-background p-2 transition hover:bg-muted/60">
                        <ArrowLeft class="size-5" />
                    </Link>
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">Chi tiết kho</h1>
                        <p class="text-sm text-muted-foreground">Thông tin tổng quan và chỉ số hoạt động của kho.</p>
                    </div>
                </div>

                <Link :href="tenantRoutes.warehouses.edit(warehouse.id)" class="inline-flex items-center gap-2 rounded-xl border border-border/70 bg-background px-3 py-2 text-sm transition hover:bg-muted/60">
                    <Pencil class="size-4" />
                    Sửa
                </Link>
            </div>

            <section class="rounded-2xl border border-border/70 bg-card p-5">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Mã kho</dt>
                        <dd class="mt-1 font-mono text-sm">{{ warehouse.code || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Trạng thái</dt>
                        <dd class="mt-1">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="warehouse.is_active ? 'bg-primary/15 text-primary' : 'bg-muted text-muted-foreground'">
                                {{ warehouse.is_active ? 'Hoạt động' : 'Tắt' }}
                            </span>
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Tên kho</dt>
                        <dd class="mt-1 text-base font-semibold">{{ warehouse.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Địa chỉ</dt>
                        <dd class="mt-1 text-sm">{{ warehouse.address || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Điện thoại</dt>
                        <dd class="mt-1 text-sm">{{ warehouse.phone || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Kho mặc định</dt>
                        <dd class="mt-1 text-sm">{{ warehouse.is_default ? 'Có' : 'Không' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Số bản ghi tồn kho</dt>
                        <dd class="mt-1 text-sm">{{ warehouse.stocks_count ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Số phiếu nhập</dt>
                        <dd class="mt-1 text-sm">{{ warehouse.import_receipts_count ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted-foreground">Số biến động kho</dt>
                        <dd class="mt-1 text-sm">{{ warehouse.stock_movements_count ?? 0 }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </TenantLayout>
</template>
