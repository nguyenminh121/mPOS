<script setup lang="ts">
import { ref } from 'vue';
import { Plus } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import AddProductModal from '@/components/tenant/products/modals/AddProductModal.vue';
import SearchProduct from '@/components/ui/product-search/SearchProduct.vue';
import { normalizeProductSearchText } from '@/composables/products/productSearch';
import { tenantRoutes } from '@/lib/tenantRoutes';
import type { AddProductPayload, CategoryOption, VariantOption, WarehouseOption } from '@/composables/pos/models';
import type { UomOption } from '@/composables/pos/models';

// ── Props / Emits ─────────────────────────────────────────────────────────────

const props = withDefaults(defineProps<{
    variants: VariantOption[];
    modelValue: string;
    selectedWarehouseId?: number | null;
    placeholder?: string;
    disabled?: boolean;
    categories?: CategoryOption[];
    uoms?: UomOption[];
    warehouses?: WarehouseOption[];
    defaultWarehouseId?: number | null;
}>(), {
    selectedWarehouseId: null,
    placeholder: 'Tìm hàng hóa',
    disabled: false,
    categories: () => [],
    uoms: () => [],
    warehouses: () => [],
    defaultWarehouseId: null,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
    select: [variant: VariantOption];
    productAdded: [variant: VariantOption];
}>();

// ── Add-product modal state ───────────────────────────────────────────────────

const addModalOpen = ref(false);
const addModalProcessing = ref(false);
const seedCode = ref('');
const searchRef = ref<InstanceType<typeof SearchProduct> | null>(null);

const openAddModal = () => {
    const rawQuery = props.modelValue.trim();
    seedCode.value = rawQuery === '' ? '' : normalizeProductSearchText(rawQuery).replace(/\s+/g, '').toUpperCase();
    addModalOpen.value = true;
};

// ── CSRF token ────────────────────────────────────────────────────────────────

const csrfToken = (): string => {
    const meta = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content;
    if (meta) return meta;
    const cookie = document.cookie.split('; ').find((r) => r.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.split('=')[1] ?? '') : '';
};

// ── Submit handler ────────────────────────────────────────────────────────────

const handleAddProductSubmit = async (payload: AddProductPayload) => {
    addModalProcessing.value = true;

    const token = csrfToken();
    const formData = new FormData();

    formData.append('name', payload.name);
    formData.append('sku', payload.sku);
    formData.append('barcode', payload.barcode);
    formData.append('base_unit', payload.base_unit);
    formData.append('is_active', '1');

    if (payload.category_id !== null) {
        formData.append('category_id', String(payload.category_id));
    }

    formData.append('initial_stock', String(Number(payload.initial_stock || 0)));
    if (payload.warehouse_id !== null) {
        formData.append('warehouse_id', String(payload.warehouse_id));
    }

    formData.append('variants[0][name]', payload.variant_name ?? payload.name);
    formData.append('variants[0][sku]', payload.sku);
    formData.append('variants[0][barcode]', payload.barcode);
    formData.append('variants[0][base_unit]', payload.base_unit);
    formData.append('variants[0][price]', String(Number(payload.retail_price || 0)));
    formData.append('variants[0][cost_price]', String(Number(payload.cost_price || 0)));
    formData.append('variants[0][wholesale_price]', String(Number(payload.wholesale_price || 0)));
    formData.append('variants[0][currency]', 'VND');
    formData.append('variants[0][is_active]', '1');
    formData.append('variants[0][is_default]', '1');

    if (payload.image_file) {
        formData.append('images[0][_file]', payload.image_file);
        formData.append('images[0][is_default]', '1');
    }

    if (token) formData.append('_token', token);

    try {
        const response = await fetch(tenantRoutes.products.store(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
            },
            body: formData,
        });

        const body = await response.json().catch(() => null);

        if (!response.ok) {
            alert(typeof body?.message === 'string' ? body.message : 'Không thể tạo sản phẩm mới.');
            return;
        }

        const createdVariant = body?.product?.variant;
        const createdProductName = body?.product?.name ?? payload.name;

        if (createdVariant && typeof createdVariant.id === 'number') {
            const newVariant: VariantOption = {
                id: Number(createdVariant.id),
                product_id: Number(body?.product?.id ?? 0) || null,
                product_name: createdProductName,
                name: createdVariant.name ?? payload.name,
                sku: createdVariant.sku ?? payload.sku,
                barcode: createdVariant.barcode ?? payload.barcode,
                base_unit: createdVariant.base_unit ?? payload.base_unit,
                price: Number(createdVariant.price ?? payload.retail_price ?? 0),
                unit_options: [],
                total_stock: Number(payload.initial_stock || 0),
                stock_by_warehouse: payload.warehouse_id
                    ? [{
                        warehouse_id: payload.warehouse_id,
                        warehouse_name: props.warehouses.find((w) => w.id === payload.warehouse_id)?.name ?? null,
                        quantity: Number(payload.initial_stock || 0),
                    }]
                    : [],
            };

            emit('productAdded', newVariant);
        }

        addModalOpen.value = false;
        emit('update:modelValue', '');
        searchRef.value?.focus();
    } finally {
        addModalProcessing.value = false;
    }
};

// ── Expose ────────────────────────────────────────────────────────────────────

const focus = () => searchRef.value?.focus();
defineExpose({ focus });
</script>

<template>
    <SearchProduct
        ref="searchRef"
        :variants="variants"
        :model-value="modelValue"
        :selected-warehouse-id="selectedWarehouseId"
        :placeholder="placeholder"
        :disabled="disabled"
        @update:model-value="emit('update:modelValue', $event)"
        @select="emit('select', $event)"
    >
        <!-- Footer: add product button -->
        <template #footer>
            <div class="border-t border-border/60 p-1.5">
                <Button
                    type="button"
                    variant="ghost"
                    class="h-9 w-full justify-start gap-2 text-sm font-normal text-muted-foreground hover:text-foreground"
                    @click="openAddModal"
                >
                    <Plus class="size-4 shrink-0" />
                    Thêm hàng hóa mới
                    <span v-if="modelValue.trim()" class="ml-auto truncate font-mono text-xs opacity-60">
                        "{{ modelValue.trim() }}"
                    </span>
                </Button>
            </div>
        </template>
    </SearchProduct>

    <!-- Add product modal -->
    <AddProductModal
        :open="addModalOpen"
        :categories="categories"
        :uoms="uoms"
        :warehouses="warehouses"
        :default-warehouse-id="defaultWarehouseId ?? selectedWarehouseId"
        :initial-sku="seedCode"
        :processing="addModalProcessing"
        @update:open="addModalOpen = $event"
        @submit="handleAddProductSubmit"
    />
</template>
