<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from 'lucide-vue-next';
import { computed } from 'vue';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

type WarehousePayload = {
    id: number;
    code?: string | null;
    name: string;
    address?: string | null;
    phone?: string | null;
    is_active: boolean;
    is_default: boolean;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    warehouse: WarehousePayload | null;
}>();

const isEdit = computed(() => props.mode === 'edit');

const form = useForm({
    code: props.warehouse?.code ?? '',
    name: props.warehouse?.name ?? '',
    address: props.warehouse?.address ?? '',
    phone: props.warehouse?.phone ?? '',
    is_active: props.warehouse?.is_active ?? true,
    is_default: props.warehouse?.is_default ?? false,
});

const submit = () => {
    if (isEdit.value && props.warehouse) {
        form.put(tenantRoutes.warehouses.update(props.warehouse.id));
        return;
    }

    form.post(tenantRoutes.warehouses.store());
};
</script>

<template>
    <Head :title="isEdit ? 'Sửa kho' : 'Thêm kho'" />
    <TenantLayout>
        <div class="mx-auto max-w-3xl space-y-6">
            <div class="flex items-center gap-3">
                <Link :href="tenantRoutes.warehouses.index()" class="rounded-xl border border-border/70 bg-background p-2 transition hover:bg-muted/60">
                    <ArrowLeft class="size-5" />
                </Link>
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ isEdit ? 'Sửa kho' : 'Thêm kho mới' }}</h1>
                    <p class="text-sm text-muted-foreground">Thiết lập thông tin kho và trạng thái sử dụng.</p>
                </div>
            </div>

            <form class="space-y-5 rounded-2xl border border-border/70 bg-card p-5" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium">Mã kho</label>
                    <input v-model="form.code" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring" placeholder="VD: WH-HN" />
                    <p v-if="form.errors.code" class="mt-1 text-xs text-destructive">{{ form.errors.code }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Tên kho <span class="text-destructive">*</span></label>
                    <input v-model="form.name" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring" placeholder="Nhập tên kho" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Địa chỉ</label>
                    <textarea v-model="form.address" rows="3" class="w-full resize-none rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring" placeholder="Địa chỉ kho (tùy chọn)" />
                    <p v-if="form.errors.address" class="mt-1 text-xs text-destructive">{{ form.errors.address }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Điện thoại</label>
                    <input v-model="form.phone" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring" placeholder="Số điện thoại liên hệ" />
                    <p v-if="form.errors.phone" class="mt-1 text-xs text-destructive">{{ form.errors.phone }}</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input v-model="form.is_active" type="checkbox" class="rounded" />
                        Kho đang hoạt động
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input v-model="form.is_default" type="checkbox" class="rounded" />
                        Đặt làm kho mặc định
                    </label>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-50">
                        <Save class="size-4" />
                        {{ isEdit ? 'Lưu cập nhật' : 'Tạo kho' }}
                    </button>
                    <Link :href="tenantRoutes.warehouses.index()" class="rounded-xl border border-border/70 bg-background px-4 py-2.5 text-sm transition hover:bg-muted/60">Hủy</Link>
                </div>
            </form>
        </div>
    </TenantLayout>
</template>
