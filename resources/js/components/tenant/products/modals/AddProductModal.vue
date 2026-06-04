<script setup lang="ts">
import { Camera, ChevronDown, Plus, Save } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import { generateCode as generateProductCodeRoute } from '@/actions/App/Domain/Tenant/Products/Controllers/ProductController';
import CategoryFormModal from '@/components/tenant/categories/modals/CategoryFormModal.vue';
import GenerateProductCode from '@/components/tenant/products/GenerateProductCode.vue';
import UOMFormModal from '@/components/tenant/uoms/modals/UOMFormModal.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type CategoryOption = {
    id: number;
    code?: string | null;
    name: string;
    description?: string | null;
    parent_id?: number | null;
    is_active: boolean;
};

type UomOption = {
    id: number;
    name: string;
    symbol?: string | null;
};

type UomModalOption = {
    id: number;
    name: string;
    symbol?: string | null;
    base_uom_id?: number | null;
    is_active: boolean;
};

type WarehouseOption = {
    id: number;
    name: string;
};

type CategoryTreeOption = CategoryOption & {
    treeLabel: string;
    fullLabel: string;
};

type ProductCreatePayload = {
    barcode: string;
    sku: string;
    name: string;
    variant_name: string | null;
    base_unit: string;
    cost_price: number;
    retail_price: number;
    wholesale_price: number;
    initial_stock: number;
    warehouse_id: number | null;
    category_id: number | null;
    image_file: File | null;
};

const props = withDefaults(defineProps<{
    open: boolean;
    categories?: CategoryOption[];
    uoms?: UomOption[];
    warehouses?: WarehouseOption[];
    defaultWarehouseId?: number | null;
    initialBarcode?: string | null;
    initialSku?: string | null;
    processing?: boolean;
}>(), {
    categories: () => [],
    uoms: () => [],
    warehouses: () => [],
    defaultWarehouseId: null,
    initialBarcode: null,
    initialSku: null,
    processing: false,
});

const emit = defineEmits<{
    'update:open': [value: boolean];
    submit: [payload: ProductCreatePayload];
}>();

const imageInputRef = ref<HTMLInputElement | null>(null);
const imagePreview = ref<string | null>(null);
const imageFile = ref<File | null>(null);
const isVariantAuto = ref(true);

const categoryModalOpen = ref(false);
const categoryOptions = ref<CategoryOption[]>([]);
const categoryDropdownOpen = ref(false);
const uomModalOpen = ref(false);
const uomDropdownOpen = ref(false);
const uomSearchQuery = ref('');
const uomOptions = ref<UomOption[]>([]);
const isGeneratingCode = ref(false);

const form = reactive({
    barcode: '',
    sku: '',
    name: '',
    variant_name: '',
    base_unit: 'CÁI',
    cost_price: 0,
    retail_price: 0,
    wholesale_price: 0,
    initial_stock: 0,
    warehouse_id: null as number | null,
    category_id: null as number | null,
});

const errors = reactive<Record<string, string>>({});

const resetForm = () => {
    form.barcode = props.initialBarcode ?? '';
    form.sku = props.initialSku ?? '';
    form.name = '';
    form.variant_name = '';
    form.base_unit = 'CÁI';
    form.cost_price = 0;
    form.retail_price = 0;
    form.wholesale_price = 0;
    form.initial_stock = 0;
    form.warehouse_id = props.defaultWarehouseId ?? props.warehouses[0]?.id ?? null;
    form.category_id = null;

    imagePreview.value = null;
    imageFile.value = null;
    isVariantAuto.value = true;
    categoryDropdownOpen.value = false;
    uomDropdownOpen.value = false;
    categoryModalOpen.value = false;
    uomModalOpen.value = false;
    uomSearchQuery.value = '';

    Object.keys(errors).forEach((key) => delete errors[key]);
};

watch(
    () => props.categories,
    (value) => {
        categoryOptions.value = [...value];
    },
    { immediate: true },
);

watch(
    () => props.uoms,
    (value) => {
        uomOptions.value = [...value];
    },
    { immediate: true },
);

watch(
    () => [props.open, props.initialBarcode, props.initialSku],
    ([isOpen]) => {
        if (!isOpen) return;
        resetForm();
    },
    { immediate: true },
);

const uomMenuOptions = computed(() => {
    const defaultUom = { id: 0, name: 'CÁI', symbol: null };

    const fromProps = uomOptions.value
        .filter((item) => item.name.trim() !== '')
        .map((item) => ({
            id: item.id,
            name: item.name,
            symbol: item.symbol ?? null,
        }));

    const merged = [defaultUom, ...fromProps];

    const map = new Map<string, { id: number; name: string; symbol: string | null }>();
    for (const item of merged) {
        map.set(item.name.toLowerCase(), item);
    }

    return Array.from(map.values());
});

const filteredUomMenuOptions = computed(() => {
    const query = uomSearchQuery.value.trim().toLowerCase();
    if (query === '') {
        return uomMenuOptions.value;
    }

    return uomMenuOptions.value.filter((item) => {
        const text = `${item.name} ${item.symbol ?? ''}`.toLowerCase();
        return text.includes(query);
    });
});

const uomModalOptions = computed<UomModalOption[]>(() => {
    return uomOptions.value.map((item) => ({
        id: item.id,
        name: item.name,
        symbol: item.symbol ?? null,
        base_uom_id: null,
        is_active: true,
    }));
});

const selectedCategoryLabel = computed(() => {
    if (form.category_id === null) return 'Không chọn danh mục';

    const selected = categoryTreeOptions.value.find((item) => item.id === form.category_id);
    if (!selected) return 'Không chọn danh mục';

    return selected.fullLabel;
});

const categoryTreeOptions = computed<CategoryTreeOption[]>(() => {
    const groupedByParent = new Map<number | null, CategoryOption[]>();

    for (const category of categoryOptions.value) {
        const parentKey = category.parent_id ?? null;
        const siblings = groupedByParent.get(parentKey) ?? [];
        siblings.push(category);
        groupedByParent.set(parentKey, siblings);
    }

    for (const [parentId, siblings] of groupedByParent.entries()) {
        groupedByParent.set(parentId, [...siblings].sort((left, right) => left.name.localeCompare(right.name, 'vi')));
    }

    const flattened: CategoryTreeOption[] = [];

    const walk = (parentId: number | null, depth: number, path: string[], visited: Set<number>) => {
        const nodes = groupedByParent.get(parentId) ?? [];

        for (const node of nodes) {
            if (visited.has(node.id)) {
                continue;
            }

            const nextPath = [...path, node.name];

            flattened.push({
                ...node,
                treeLabel: `${'— '.repeat(depth)}${node.name}`,
                fullLabel: nextPath.join(' / '),
            });

            const nextVisited = new Set(visited);
            nextVisited.add(node.id);
            walk(node.id, depth + 1, nextPath, nextVisited);
        }
    };

    walk(null, 0, [], new Set<number>());

    return flattened;
});

const selectedUomLabel = computed(() => {
    const selected = uomMenuOptions.value.find((item) => item.name === form.base_unit);
    if (!selected) return form.base_unit;

    return selected.symbol ? `${selected.name} (${selected.symbol})` : selected.name;
});

const selectedWarehouseLabel = computed(() => {
    if (form.warehouse_id === null) return 'Không chọn kho';

    return props.warehouses.find((item) => item.id === form.warehouse_id)?.name ?? 'Không chọn kho';
});

const categoryMenuValue = computed({
    get: () => (form.category_id === null ? '' : String(form.category_id)),
    set: (value: string) => {
        form.category_id = value === '' ? null : Number(value);
    },
});

const warehouseMenuValue = computed({
    get: () => (form.warehouse_id === null ? '' : String(form.warehouse_id)),
    set: (value: string) => {
        form.warehouse_id = value === '' ? null : Number(value);
    },
});

const uomMenuValue = computed({
    get: () => form.base_unit,
    set: (value: string) => {
        form.base_unit = value;
    },
});

const openUomModal = () => {
    uomDropdownOpen.value = false;
    uomModalOpen.value = true;
};

const handleUomSaved = (uom: UomModalOption) => {
    const existedIndex = uomOptions.value.findIndex((item) => item.id === uom.id);

    if (existedIndex >= 0) {
        uomOptions.value[existedIndex] = uom;
    } else {
        uomOptions.value.unshift(uom);
    }

    form.base_unit = uom.name;
    uomSearchQuery.value = '';
};

watch(
    () => form.name,
    (value) => {
        if (!isVariantAuto.value) return;
        form.variant_name = value.trim();
    },
);

watch(
    () => form.variant_name,
    (value) => {
        if (value.trim() === '' || value.trim() === form.name.trim()) {
            isVariantAuto.value = true;
            return;
        }

        isVariantAuto.value = false;
    },
);

const openCategoryModal = () => {
    categoryDropdownOpen.value = false;
    categoryModalOpen.value = true;
};

const handleCategorySaved = (category: CategoryOption) => {
    const existedIndex = categoryOptions.value.findIndex((item) => item.id === category.id);

    if (existedIndex >= 0) {
        categoryOptions.value[existedIndex] = category;
    } else {
        categoryOptions.value.unshift(category);
    }

    form.category_id = category.id;
};

const handleImagePick = () => {
    imageInputRef.value?.click();
};

const handleImageChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;

    if (!file) return;
    if (!file.type.startsWith('image/')) return;

    imageFile.value = file;
    imagePreview.value = URL.createObjectURL(file);
};

const generateCode = async () => {
    const productName = form.name.trim();
    if (productName === '') {
        errors.name = 'Vui lòng nhập tên sản phẩm trước khi tạo mã.';
        return;
    }

    isGeneratingCode.value = true;
    delete errors.sku;
    delete errors.barcode;

    try {
        const url = generateProductCodeRoute.url({
            query: { name: productName },
        });

        const response = await fetch(url, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => null);
            const firstError = payload?.errors
                ? Object.values(payload.errors).flat()[0]
                : payload?.message;

            const message = typeof firstError === 'string' ? firstError : 'Không thể tạo mã tự động.';
            errors.sku = message;
            errors.barcode = message;
            return;
        }

        const payload = await response.json();
        const generatedSku = String(payload?.sku ?? '').trim();
        const generatedBarcode = String(payload?.barcode ?? '').trim();

        if (generatedSku !== '') {
            form.sku = generatedSku;
        }

        if (generatedBarcode !== '') {
            form.barcode = generatedBarcode;
        }
    } catch {
        const message = 'Không thể tạo mã tự động. Vui lòng thử lại.';
        errors.sku = message;
        errors.barcode = message;
    } finally {
        isGeneratingCode.value = false;
    }
};

const validate = () => {
    Object.keys(errors).forEach((key) => delete errors[key]);

    if (form.name.trim() === '') {
        errors.name = 'Tên sản phẩm là bắt buộc.';
    }

    if (form.barcode.trim() === '') {
        errors.barcode = 'Mã vạch là bắt buộc.';
    }

    if (form.sku.trim() === '') {
        errors.sku = 'SKU là bắt buộc.';
    }

    if (form.initial_stock < 0) {
        errors.initial_stock = 'Tồn kho ban đầu không được âm.';
    }

    if (form.cost_price < 0 || form.retail_price < 0 || form.wholesale_price < 0) {
        errors.pricing = 'Giá nhập / bán không được âm.';
    }

    return Object.keys(errors).length === 0;
};

const submit = () => {
    if (!validate()) return;

    emit('submit', {
        barcode: form.barcode.trim(),
        sku: form.sku.trim(),
        name: form.name.trim(),
        variant_name: form.variant_name.trim() === '' ? null : form.variant_name.trim(),
        base_unit: form.base_unit,
        cost_price: Number(form.cost_price || 0),
        retail_price: Number(form.retail_price || 0),
        wholesale_price: Number(form.wholesale_price || 0),
        initial_stock: Number(form.initial_stock || 0),
        warehouse_id: form.warehouse_id,
        category_id: form.category_id,
        image_file: imageFile.value,
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>Thêm hàng hóa mới</DialogTitle>
                <DialogDescription>Thêm nhanh sản phẩm chưa có trong hệ thống ngay tại modal.</DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-[180px_minmax(0,1fr)]">
                    <div>
                        <Label class="mb-2">Ảnh sản phẩm</Label>
                        <input ref="imageInputRef" type="file" accept="image/*" class="hidden" @change="handleImageChange" />
                        <button
                            type="button"
                            class="flex h-40 w-full items-center justify-center rounded-xl border border-dashed border-border bg-muted/30 text-muted-foreground transition hover:bg-muted"
                            @click="handleImagePick"
                        >
                            <img v-if="imagePreview" :src="imagePreview" alt="Ảnh sản phẩm" class="h-full w-full rounded-xl object-cover" />
                            <span v-else class="inline-flex flex-col items-center gap-1 text-sm">
                                <Camera class="size-5" />
                                Tải ảnh
                            </span>
                        </button>
                    </div>

                    <div class="space-y-3">
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end">
                            <div>
                                <Label class="mb-1">Mã vạch (Barcode) *</Label>
                                <Input v-model="form.barcode" placeholder="Quét hoặc nhập mã vạch" />
                                <p v-if="errors.barcode" class="mt-1 text-xs text-destructive">{{ errors.barcode }}</p>
                            </div>

                            <div>
                                <Label class="mb-1">SKU sản phẩm *</Label>
                                <Input v-model="form.sku" placeholder="Nhập SKU" />
                                <p v-if="errors.sku" class="mt-1 text-xs text-destructive">{{ errors.sku }}</p>
                            </div>

                            <div class="flex md:justify-end">
                                <GenerateProductCode
                                    :loading="isGeneratingCode"
                                    :disabled="form.name.trim() === ''"
                                    @generate="generateCode"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <Label class="mb-1">Tên sản phẩm *</Label>
                                <Input v-model="form.name" placeholder="Nhập tên sản phẩm" />
                                <p class="mt-1 text-xs text-muted-foreground">Nhập tên sản phẩm để tự động tạo mã SKU và mã vạch</p>
                                <p v-if="errors.name" class="mt-1 text-xs text-destructive">{{ errors.name }}</p>
                            </div>

                            <div>
                                <Label class="mb-1">Tên biến thể</Label>
                                <Input v-model="form.variant_name" placeholder="VD: 350ml / Màu đỏ" />
                                <p class="mt-1 text-xs text-muted-foreground">Mặc định tự gán theo tên sản phẩm. Chỉ đổi khi cần biến thể khác.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <Label class="mb-1">UOM</Label>
                        <DropdownMenu :open="uomDropdownOpen" @update:open="(value) => (uomDropdownOpen = value)">
                            <DropdownMenuTrigger as-child>
                                <Button type="button" variant="outline" class="h-9 w-full justify-between font-normal">
                                    <span class="truncate text-left">{{ selectedUomLabel }}</span>
                                    <ChevronDown class="size-4 text-muted-foreground" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-56">
                                <DropdownMenuLabel>Chọn đơn vị tính</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <div class="p-2" @click.stop>
                                    <Input v-model="uomSearchQuery" class="h-8" placeholder="Tìm đơn vị tính" />
                                </div>
                                <div class="px-2 pb-2" @click.stop>
                                    <Button type="button" variant="secondary" class="h-8 w-full justify-start" @click="openUomModal">
                                        <Plus class="size-4" />
                                        Thêm mới đơn vị tính
                                    </Button>
                                </div>
                                <DropdownMenuSeparator />
                                <DropdownMenuRadioGroup v-model="uomMenuValue">
                                    <DropdownMenuRadioItem v-for="uom in filteredUomMenuOptions" :key="uom.name" :value="uom.name">
                                        {{ uom.symbol ? `${uom.name} (${uom.symbol})` : uom.name }}
                                    </DropdownMenuRadioItem>
                                </DropdownMenuRadioGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    <div>
                        <Label class="mb-1">Danh mục hàng hóa</Label>
                        <DropdownMenu :open="categoryDropdownOpen" @update:open="(value) => (categoryDropdownOpen = value)">
                            <DropdownMenuTrigger as-child>
                                <Button type="button" variant="outline" class="h-9 w-full justify-between font-normal">
                                    <span class="truncate text-left">{{ selectedCategoryLabel }}</span>
                                    <ChevronDown class="size-4 text-muted-foreground" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-60">
                                <DropdownMenuLabel>Danh mục hàng hóa</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <div class="p-2" @click.stop>
                                    <Button type="button" variant="secondary" class="h-8 w-full justify-start" @click="openCategoryModal">
                                        <Plus class="size-4" />
                                        Thêm mới danh mục
                                    </Button>
                                </div>
                                <DropdownMenuSeparator />
                                <DropdownMenuRadioGroup v-model="categoryMenuValue">
                                    <DropdownMenuRadioItem value="">Không chọn danh mục</DropdownMenuRadioItem>
                                    <DropdownMenuRadioItem v-for="category in categoryTreeOptions" :key="category.id" :value="String(category.id)">
                                        {{ category.code ? `${category.treeLabel} (${category.code})` : category.treeLabel }}
                                    </DropdownMenuRadioItem>
                                </DropdownMenuRadioGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div>
                        <Label class="mb-1">Giá nhập</Label>
                        <Input v-model.number="form.cost_price" type="number" min="0" step="1000" />
                    </div>

                    <div>
                        <Label class="mb-1">Giá bán lẻ</Label>
                        <Input v-model.number="form.retail_price" type="number" min="0" step="1000" />
                    </div>

                    <div>
                        <Label class="mb-1">Giá bán buôn</Label>
                        <Input v-model.number="form.wholesale_price" type="number" min="0" step="1000" />
                    </div>
                </div>

                <p v-if="errors.pricing" class="text-xs text-destructive">{{ errors.pricing }}</p>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <Label class="mb-1">Kho áp dụng tồn đầu</Label>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button type="button" variant="outline" class="h-9 w-full justify-between font-normal">
                                    <span class="truncate text-left">{{ selectedWarehouseLabel }}</span>
                                    <ChevronDown class="size-4 text-muted-foreground" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-56">
                                <DropdownMenuLabel>Chọn kho</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuRadioGroup v-model="warehouseMenuValue">
                                    <DropdownMenuRadioItem value="">Không chọn kho</DropdownMenuRadioItem>
                                    <DropdownMenuRadioItem v-for="warehouse in props.warehouses" :key="warehouse.id" :value="String(warehouse.id)">
                                        {{ warehouse.name }}
                                    </DropdownMenuRadioItem>
                                </DropdownMenuRadioGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    <div>
                        <Label class="mb-1">Tồn kho ban đầu</Label>
                        <Input v-model.number="form.initial_stock" type="number" min="0" step="1" />
                        <p v-if="errors.initial_stock" class="mt-1 text-xs text-destructive">{{ errors.initial_stock }}</p>
                    </div>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button type="button" variant="outline" @click="emit('update:open', false)">Hủy</Button>
                <Button type="button" :disabled="processing" @click="submit">
                    <Save class="size-4" />
                    {{ processing ? 'Đang lưu...' : 'Lưu hàng hóa' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <CategoryFormModal
        v-model:open="categoryModalOpen"
        mode="create"
        :category="null"
        :categories="categoryOptions"
        @saved="handleCategorySaved"
    />

    <UOMFormModal
        v-model:open="uomModalOpen"
        mode="create"
        :uom="null"
        :uoms="uomModalOptions"
        @saved="handleUomSaved"
    />
</template>
