<script setup lang="ts">
import { Loader2, PackagePlus, Trash2, CheckCircle2, XCircle, Upload, Download, CircleAlert, Plus } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import UOMFormModal from '@/components/tenant/uoms/modals/UOMFormModal.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogDescription,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import SearchProduct from '@/components/ui/product-search/SearchProduct.vue';
import type { VariantOption } from '@/composables/pos/models';
import { useProduct } from '@/composables/products/useProduct';

type Uom = { id: number; name: string; symbol?: string | null; base_uom_id?: number | null; is_active?: boolean };
type Warehouse = { id: number; name: string };

const props = defineProps<{
    modelValue: boolean;
    uoms: Uom[];
    warehouses: Warehouse[];
    variants: VariantOption[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    success: [];
}>();

const fileInputRef = ref<HTMLInputElement | null>(null);
const localUoms = ref<Uom[]>([]);
const uomSearch = ref('');
const addUomOpen = ref(false);
const targetRowKeyForUom = ref<string | null>(null);
const activeUomRowKey = ref<string | null>(null);

const {
    activeTab,
    rows,
    searchQuery,
    submitting,
    submitted,
    globalError,
    duplicateNotice,
    uploadedFileName,
    uploadError,
    uploadSummary,
    canSubmit,
    failedRows,
    doneCount,
    removeRow,
    addVariantFromSearch,
    addRowFromQuery,
    importFile,
    downloadTemplate,
    reset,
    submitAll,
} = useProduct({
    warehouses: props.warehouses,
});

watch(() => props.uoms, (value) => {
    localUoms.value = [...value];
}, { immediate: true });

const filteredUoms = computed(() => {
    const q = uomSearch.value.trim().toLowerCase();
    if (!q) return localUoms.value;
    return localUoms.value.filter((uom) => {
        const name = (uom.name ?? '').toLowerCase();
        const symbol = (uom.symbol ?? '').toLowerCase();
        return name.includes(q) || symbol.includes(q);
    });
});

const uomOptionsForModal = computed(() =>
    localUoms.value.map((uom) => ({
        id: uom.id,
        name: uom.name,
        symbol: uom.symbol,
        base_uom_id: uom.base_uom_id ?? null,
        is_active: uom.is_active ?? true,
    })),
);

const setUomForRow = (rowKey: string, uomName: string) => {
    const row = rows.value.find((item) => item._key === rowKey);
    if (!row || row._done) return;
    row.base_unit = uomName;
    activeUomRowKey.value = null;
    uomSearch.value = '';
};

const openUomPicker = (rowKey: string, value: string) => {
    activeUomRowKey.value = rowKey;
    targetRowKeyForUom.value = rowKey;
    uomSearch.value = value;
};

const onUomInput = (rowKey: string, value: string) => {
    const row = rows.value.find((item) => item._key === rowKey);
    if (!row || row._done) return;
    row.base_unit = value;
    targetRowKeyForUom.value = rowKey;
    activeUomRowKey.value = rowKey;
    uomSearch.value = value;
};

const openAddUom = (rowKey: string) => {
    targetRowKeyForUom.value = rowKey;
    addUomOpen.value = true;
};

const onUomSaved = (uom: Uom) => {
    const index = localUoms.value.findIndex((item) => item.id === uom.id);
    if (index >= 0) {
        localUoms.value[index] = uom;
    } else {
        localUoms.value.unshift(uom);
    }

    if (targetRowKeyForUom.value) {
        setUomForRow(targetRowKeyForUom.value, uom.name);
    }

    addUomOpen.value = false;
};

const handleClickOutsideUom = (event: MouseEvent) => {
    const target = event.target as HTMLElement | null;
    if (!target?.closest('[data-uom-picker]')) {
        activeUomRowKey.value = null;
    }
};

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutsideUom);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutsideUom);
});

function close() {
    if (submitting.value) return;
    emit('update:modelValue', false);
    reset();
}

async function onSubmit() {
    await submitAll(() => {
        emit('success');
        emit('update:modelValue', false);
        reset();
    });
}

function triggerUpload() {
    fileInputRef.value?.click();
}

async function onFileChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    try {
        await importFile(file);
    } catch (error) {
        const message = error instanceof Error ? error.message : 'Không thể xử lý đơn vị tính từ file import.';
        uploadError.value = message;
    } finally {
        input.value = '';
    }
}
</script>

<template>
    <Dialog :open="modelValue" @update:open="(v) => !v && close()">
        <DialogContent
            class="w-[90vw] max-w-[90vw] sm:!max-w-[90vw] h-[85vh] max-h-[85vh] min-h-[70vh] flex flex-col gap-0 p-0 overflow-hidden"
            :show-close-button="false"
        >
            <DialogHeader class="flex flex-row items-center justify-between border-b px-5 py-4 shrink-0">
                <div class="flex items-center gap-2">
                    <PackagePlus class="size-5 text-primary" />
                    <DialogTitle class="text-base font-semibold">Nhập danh sách hàng hóa</DialogTitle>
                    <DialogDescription class="sr-only">
                        Tạo danh sách hàng hóa bằng nhập thủ công hoặc tải file mẫu để import.
                    </DialogDescription>
                </div>
                <div class="flex items-center gap-3">
                    <span v-if="submitted" class="text-xs text-muted-foreground">
                        {{ doneCount }} / {{ rows.length }} đã tạo
                        <span v-if="failedRows.length > 0" class="text-destructive ml-1">• {{ failedRows.length }} lỗi</span>
                    </span>
                    <Button variant="ghost" size="icon" class="size-7" :disabled="submitting" @click="close">
                        <span class="sr-only">Đóng</span>
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </Button>
                </div>
            </DialogHeader>

            <div class="border-b px-5 py-3 flex items-center gap-2">
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-sm transition"
                    :class="activeTab === 'manual' ? 'bg-primary text-primary-foreground' : 'bg-muted/70 hover:bg-muted'"
                    :disabled="submitting"
                    @click="activeTab = 'manual'"
                >
                    Nhập thủ công
                </button>
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-sm transition"
                    :class="activeTab === 'upload' ? 'bg-primary text-primary-foreground' : 'bg-muted/70 hover:bg-muted'"
                    :disabled="submitting"
                    @click="activeTab = 'upload'"
                >
                    Nhập từ file
                </button>
            </div>

            <div class="flex-1 min-h-0 overflow-hidden">
                <div v-if="activeTab === 'manual'" class="h-full p-4 space-y-3 flex flex-col">
                    <div class="space-y-2 shrink-0">
                        <p class="text-sm text-muted-foreground">
                            Tìm sản phẩm có sẵn để thêm nhanh vào danh sách.
                        </p>
                        <SearchProduct
                            v-model="searchQuery"
                            :variants="props.variants"
                            placeholder="Tìm theo tên / SKU / barcode"
                            @select="addVariantFromSearch"
                            @enter-no-match="addRowFromQuery"
                        />
                    </div>

                    <div
                        v-if="duplicateNotice"
                        class="shrink-0 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200"
                    >
                        <div class="flex items-center gap-2">
                            <CircleAlert class="size-4" />
                            <span>{{ duplicateNotice }}</span>
                        </div>
                    </div>

                    <div class="flex-1 min-h-0 border rounded-lg overflow-auto">
                        <table class="w-full min-w-[1100px] text-sm border-collapse">
                            <thead class="sticky top-0 z-10 bg-muted/80 backdrop-blur">
                                <tr>
                                    <th class="w-8 px-2 py-2.5 text-center text-xs font-medium text-muted-foreground">#</th>
                                    <th class="min-w-[120px] px-2 py-2.5 text-left text-xs font-medium">SKU</th>
                                    <th class="min-w-[120px] px-2 py-2.5 text-left text-xs font-medium">Barcode</th>
                                    <th class="min-w-[180px] px-2 py-2.5 text-left text-xs font-medium">
                                        Tên hàng hóa <span class="text-destructive">*</span>
                                    </th>
                                    <th class="min-w-[110px] px-2 py-2.5 text-left text-xs font-medium">
                                        Đơn vị tính <span class="text-destructive">*</span>
                                    </th>
                                    <th class="min-w-[96px] px-2 py-2.5 text-right text-xs font-medium">Giá vốn</th>
                                    <th class="min-w-[96px] px-2 py-2.5 text-right text-xs font-medium">
                                        Giá lẻ <span class="text-destructive">*</span>
                                    </th>
                                    <th class="min-w-[96px] px-2 py-2.5 text-right text-xs font-medium">Giá sỉ</th>
                                    <th class="min-w-[130px] px-2 py-2.5 text-left text-xs font-medium">Kho hàng</th>
                                    <th class="min-w-[86px] px-2 py-2.5 text-right text-xs font-medium">Tồn đầu kỳ</th>
                                    <th class="w-10 px-2 py-2.5"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, index) in rows"
                                    :key="row._key"
                                    class="border-t border-border/50 transition-colors"
                                    :class="{
                                        'bg-green-50/60 dark:bg-green-900/10': row._done,
                                        'bg-destructive/5': row._failed,
                                        'opacity-50 pointer-events-none': row._submitting,
                                    }"
                                >
                                    <td class="px-2 py-1.5 text-center">
                                        <span v-if="row._submitting">
                                            <Loader2 class="size-3.5 animate-spin text-muted-foreground mx-auto" />
                                        </span>
                                        <span v-else-if="row._done">
                                            <CheckCircle2 class="size-3.5 text-green-600 mx-auto" />
                                        </span>
                                        <span v-else-if="row._failed">
                                            <XCircle class="size-3.5 text-destructive mx-auto" />
                                        </span>
                                        <span v-else class="text-xs text-muted-foreground">{{ index + 1 }}</span>
                                    </td>

                                    <td class="px-2 py-1.5">
                                        <input
                                            v-model="row.sku"
                                            type="text"
                                            placeholder="SKU"
                                            :disabled="row._done"
                                            :data-import-row-key="row._key"
                                            data-import-field="sku"
                                            :class="row._errors.sku_barcode ? 'border-destructive ring-destructive/30 ring-1' : 'border-border/70'"
                                            class="w-full rounded-md border bg-background px-2 py-1.5 text-sm font-mono outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
                                        />
                                    </td>

                                    <td class="px-2 py-1.5">
                                        <input
                                            v-model="row.barcode"
                                            type="text"
                                            placeholder="Barcode"
                                            :disabled="row._done"
                                            :data-import-row-key="row._key"
                                            data-import-field="barcode"
                                            :class="row._errors.sku_barcode ? 'border-destructive ring-destructive/30 ring-1' : 'border-border/70'"
                                            class="w-full rounded-md border bg-background px-2 py-1.5 text-sm font-mono outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
                                        />
                                        <p v-if="row._errors.sku_barcode" class="mt-0.5 text-xs text-destructive">{{ row._errors.sku_barcode }}</p>
                                    </td>

                                    <td class="px-2 py-1.5">
                                        <input
                                            v-model="row.name"
                                            type="text"
                                            placeholder="Tên hàng hóa"
                                            :disabled="row._done"
                                            :data-import-row-key="row._key"
                                            data-import-field="name"
                                            :class="row._errors.name ? 'border-destructive ring-destructive/30 ring-1' : 'border-border/70'"
                                            class="w-full rounded-md border bg-background px-2 py-1.5 text-sm outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
                                        />
                                        <p v-if="row._errors.name" class="mt-0.5 text-xs text-destructive">{{ row._errors.name }}</p>
                                    </td>

                                    <td class="px-2 py-1.5">
                                        <div class="relative" data-uom-picker>
                                            <input
                                                :value="activeUomRowKey === row._key ? uomSearch : row.base_unit"
                                                type="text"
                                                placeholder="Tìm / nhập ĐVT"
                                                :disabled="row._done"
                                                :data-import-row-key="row._key"
                                                data-import-field="base_unit"
                                                :class="row._errors.base_unit ? 'border-destructive ring-destructive/30 ring-1' : 'border-border/70'"
                                                class="w-full rounded-md border bg-background px-2 py-1.5 text-sm outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
                                                @focus="openUomPicker(row._key, row.base_unit)"
                                                @input="onUomInput(row._key, ($event.target as HTMLInputElement).value)"
                                            />

                                            <div
                                                v-if="activeUomRowKey === row._key"
                                                class="absolute left-0 top-full z-40 mt-1 w-[260px] overflow-hidden rounded-md border border-border bg-popover shadow-lg"
                                            >
                                                <div class="max-h-44 overflow-y-auto p-1">
                                                    <button
                                                        v-for="uom in filteredUoms"
                                                        :key="uom.id"
                                                        type="button"
                                                        class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-xs hover:bg-muted"
                                                        @mousedown.prevent="setUomForRow(row._key, uom.name)"
                                                    >
                                                        {{ uom.symbol ? `${uom.name} (${uom.symbol})` : uom.name }}
                                                    </button>
                                                    <p v-if="filteredUoms.length === 0" class="px-2 py-1.5 text-xs text-muted-foreground">Không có dữ liệu</p>
                                                </div>
                                                <div class="sticky bottom-0 border-t border-border bg-popover p-1">
                                                    <button
                                                        type="button"
                                                        class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-xs hover:bg-muted"
                                                        @mousedown.prevent="openAddUom(row._key)"
                                                    >
                                                        <Plus class="mr-1.5 size-3.5" />
                                                        Thêm đơn vị tính mới
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <p v-if="row._errors.base_unit" class="mt-0.5 text-xs text-destructive">{{ row._errors.base_unit }}</p>
                                    </td>

                                    <td class="px-2 py-1.5">
                                        <input
                                            v-model="row.cost_price"
                                            type="number"
                                            min="0"
                                            placeholder="0"
                                            :disabled="row._done"
                                            class="w-full rounded-md border border-border/70 bg-background px-2 py-1.5 text-right text-sm outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
                                        />
                                    </td>

                                    <td class="px-2 py-1.5">
                                        <input
                                            v-model="row.retail_price"
                                            type="number"
                                            min="0"
                                            placeholder="0"
                                            :disabled="row._done"
                                            :data-import-row-key="row._key"
                                            data-import-field="retail_price"
                                            :class="row._errors.retail_price ? 'border-destructive ring-destructive/30 ring-1' : 'border-border/70'"
                                            class="w-full rounded-md border bg-background px-2 py-1.5 text-right text-sm font-medium outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
                                        />
                                        <p v-if="row._errors.retail_price" class="mt-0.5 text-xs text-destructive">{{ row._errors.retail_price }}</p>
                                    </td>

                                    <td class="px-2 py-1.5">
                                        <input
                                            v-model="row.wholesale_price"
                                            type="number"
                                            min="0"
                                            placeholder="0"
                                            :disabled="row._done"
                                            class="w-full rounded-md border border-border/70 bg-background px-2 py-1.5 text-right text-sm outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
                                        />
                                    </td>

                                    <td class="px-2 py-1.5">
                                        <select
                                            v-model="row.warehouse_id"
                                            :disabled="row._done || warehouses.length === 0"
                                            class="w-full rounded-md border border-border/70 bg-background px-2 py-1.5 text-sm outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
                                        >
                                            <option :value="null" disabled>-- Chọn kho --</option>
                                            <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">
                                                {{ wh.name }}
                                            </option>
                                        </select>
                                    </td>

                                    <td class="px-2 py-1.5">
                                        <input
                                            v-model="row.initial_inventory"
                                            type="number"
                                            min="0"
                                            step="1"
                                            placeholder="0"
                                            :disabled="row._done || !row.warehouse_id"
                                            class="w-full rounded-md border border-border/70 bg-background px-2 py-1.5 text-right text-sm outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
                                        />
                                    </td>

                                    <td class="px-2 py-1.5 text-center">
                                        <button
                                            v-if="!row._done"
                                            type="button"
                                            :disabled="submitting"
                                            class="rounded p-1 text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive disabled:pointer-events-none disabled:opacity-30"
                                            title="Xóa dòng"
                                            @click="removeRow(row._key)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-else class="h-full overflow-auto p-4 space-y-4">
                    <div class="rounded-lg border bg-muted/20 p-4 space-y-3">
                        <h4 class="text-sm font-semibold">Tải file theo mẫu</h4>
                        <p class="text-sm text-muted-foreground">
                            Dùng đúng cấu trúc của file mẫu rồi tải lên, hệ thống sẽ tự thêm vào danh sách ở Tab 1.
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <Button type="button" variant="outline" size="sm" :disabled="submitting" @click="downloadTemplate">
                                <Download class="mr-1.5 size-3.5" />
                                Tải file mẫu
                            </Button>
                            <Button type="button" size="sm" :disabled="submitting" @click="triggerUpload">
                                <Upload class="mr-1.5 size-3.5" />
                                Chọn file để import
                            </Button>
                            <input
                                ref="fileInputRef"
                                type="file"
                                accept=".csv,.xlsx,.xls"
                                class="hidden"
                                @change="onFileChange"
                            />
                        </div>
                        <p v-if="uploadedFileName" class="text-xs text-muted-foreground">
                            File đã chọn: {{ uploadedFileName }}
                        </p>
                    </div>

                    <div
                        v-if="uploadSummary"
                        class="rounded-md border border-border bg-card px-3 py-2 text-sm"
                    >
                        Đã thêm {{ uploadSummary.added }} dòng,
                        trùng {{ uploadSummary.duplicates }} dòng,
                        bỏ qua {{ uploadSummary.skipped }} dòng,
                        lỗi {{ uploadSummary.failed ?? 0 }} dòng.
                    </div>

                    <div
                        v-if="uploadError"
                        class="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                    >
                        {{ uploadError }}
                    </div>
                </div>
            </div>

            <DialogFooter class="flex flex-row items-center justify-between border-t px-4 py-2 shrink-0">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-muted-foreground">{{ rows.length }} dòng</span>
                </div>

                <div class="flex items-center gap-2">
                    <p v-if="globalError" class="text-xs text-destructive">{{ globalError }}</p>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="submitting"
                        @click="close"
                    >
                        Hủy
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :disabled="!canSubmit"
                        @click="onSubmit"
                    >
                        <Loader2 v-if="submitting" class="size-3.5 mr-1.5 animate-spin" />
                        <PackagePlus v-else class="size-3.5 mr-1.5" />
                        {{ submitting ? 'Đang lưu...' : 'Lưu hàng hóa' }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
        <UOMFormModal
            :open="addUomOpen"
            mode="create"
            :uom="null"
            :uoms="uomOptionsForModal"
            @update:open="addUomOpen = $event"
            @saved="onUomSaved"
        />
    </Dialog>
</template>
