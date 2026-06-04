<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ProductPickerOption = {
    product_id: number;
    product_name: string;
    variant_id: number;
    variant_name: string;
    sku?: string | null;
    barcode?: string | null;
    base_unit?: string | null;
};

const props = defineProps<{
    open: boolean;
    products: ProductPickerOption[];
    selectedVariantIds: number[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    select: [product: ProductPickerOption];
}>();

const keyword = ref('');

watch(
    () => props.open,
    (opened) => {
        if (opened) {
            keyword.value = '';
        }
    },
);

const filteredProducts = computed(() => {
    const q = keyword.value.trim().toLowerCase();
    if (!q) {
        return props.products;
    }

    return props.products.filter((item) => {
        const haystack = [item.product_name, item.variant_name, item.sku ?? '', item.barcode ?? '']
            .join(' ')
            .toLowerCase();

        return haystack.includes(q);
    });
});

const selectedSet = computed(() => new Set(props.selectedVariantIds));

function closeModal(): void {
    emit('update:open', false);
}

function selectProduct(item: ProductPickerOption): void {
    emit('select', item);
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="max-w-3xl">
            <DialogHeader>
                <DialogTitle>Chọn sản phẩm kiểm kê</DialogTitle>
                <DialogDescription>
                    Tìm và chọn biến thể sản phẩm để thêm vào phiếu kiểm kê.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-3">
                <input
                    v-model="keyword"
                    type="text"
                    class="w-full rounded-lg border border-border/70 bg-background px-3 py-2 text-sm"
                    placeholder="Tìm theo tên, SKU hoặc mã vạch"
                />

                <div class="max-h-[420px] overflow-y-auto rounded-lg border border-border/70">
                    <table class="min-w-full text-sm">
                        <thead class="bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2">Sản phẩm</th>
                                <th class="px-3 py-2">Biến thể</th>
                                <th class="px-3 py-2">SKU / Barcode</th>
                                <th class="px-3 py-2 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="filteredProducts.length === 0">
                                <td colspan="4" class="px-3 py-6 text-center text-muted-foreground">
                                    Không tìm thấy sản phẩm phù hợp.
                                </td>
                            </tr>

                            <tr
                                v-for="item in filteredProducts"
                                :key="item.variant_id"
                                class="border-t border-border/60"
                            >
                                <td class="px-3 py-2 font-medium">{{ item.product_name }}</td>
                                <td class="px-3 py-2">
                                    {{ item.variant_name }}
                                    <span v-if="item.base_unit" class="text-xs text-muted-foreground">({{ item.base_unit }})</span>
                                </td>
                                <td class="px-3 py-2 text-muted-foreground">
                                    {{ item.sku || '-' }} / {{ item.barcode || '-' }}
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <Button
                                        type="button"
                                        size="sm"
                                        :disabled="selectedSet.has(item.variant_id)"
                                        @click="selectProduct(item)"
                                    >
                                        {{ selectedSet.has(item.variant_id) ? 'Đã chọn' : 'Chọn' }}
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" @click="closeModal">Đóng</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
