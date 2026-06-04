<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronDown,
    ImagePlus,
    Pencil,
    Plus,
    Save,
    Star,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref, onBeforeUnmount, watch } from 'vue';
import { generateCode as generateProductCodeRoute } from '@/actions/App/Domain/Tenant/Products/Controllers/ProductController';
import CategoryFormModal from '@/components/tenant/categories/modals/CategoryFormModal.vue';
import GenerateProductCode from '@/components/tenant/products/GenerateProductCode.vue';
import UOMFormModal from '@/components/tenant/uoms/modals/UOMFormModal.vue';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { productPublicRef } from '@/lib/productPublicRef';
import { tenantRoutes } from '@/lib/tenantRoutes';

// ── Types ───────────────────────────────────────────────────────────────────

type ExistingVariant = {
    id: number;
    name: string;
    sku?: string | null;
    barcode?: string | null;
    base_unit?: string | null;
    price?: string | number | null;
    cost_price?: string | number | null;
    wholesale_price?: string | number | null;
    discount_price?: string | number | null;
    currency?: string | null;
    is_active: boolean;
    is_default?: boolean;
    prices?: Array<{
        uom_id: number;
        cost_price?: string | number | null;
        retail_price?: string | number | null;
        wholesale_price?: string | number | null;
        currency?: string | null;
    }>;
    units?: Array<{
        id?: number;
        uom_id: number;
        conversion_factor: string | number;
        cost_price?: string | number | null;
        retail_price?: string | number | null;
        wholesale_price?: string | number | null;
        currency?: string | null;
    }>;
    product_storage?: Array<{
        id?: number;
        warehouse_location_id: number;
        min_stock_level?: number | string | null;
        max_stock_level?: number | string | null;
    }>;
};

type ExistingImage = {
    id: number;
    image_path?: string | null;
    is_default?: boolean;
};

type ProductData = {
    id: number;
    name: string;
    sku?: string;
    barcode?: string;
    description?: string | null;
    base_unit?: string | null;
    origin?: string | null;
    brand?: string | null;
    category_id?: number | null;
    tax_config_id?: number | null;
    is_active: boolean;
    variants?: ExistingVariant[];
    images?: ExistingImage[];
};

type CategoryOption = {
    id: number;
    code?: string | null;
    name: string;
    description?: string | null;
    parent_id?: number | null;
    is_active: boolean;
};

type CategoryTreeOption = CategoryOption & {
    treeLabel: string;
    fullLabel: string;
};

type UomOption = {
    id: number;
    name: string;
    symbol?: string | null;
    base_uom_id?: number | null;
    is_active: boolean;
};

type VariantUnitConversion = {
    id?: number;
    uom_id: number | null;
    conversion_factor: number;
    cost_price: number;
    retail_price: number;
    wholesale_price: number;
    currency: string;
};

type WarehouseLocationOption = {
    id: number;
    warehouse_id: number;
    parent_id?: number | null;
    code?: string | null;
    name: string;
    type: 'AREA' | 'FLOOR' | 'AISLE' | 'RACK' | 'SHELF' | 'BIN';
    is_active: boolean;
    warehouse?: {
        id: number;
        name: string;
    } | null;
};

type VariantStorageAssignment = {
    id?: number;
    warehouse_location_id: number | null;
    min_stock_level: number;
    max_stock_level: number;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    product: ProductData | null;
    categories: CategoryOption[];
    uoms: UomOption[];
    suppliers: Array<{ id: number; name: string }>;
    taxConfigs: Array<{ id: number; name: string }>;
    warehouseLocations: WarehouseLocationOption[];
    supplierIds: number[];
}>();

const isEdit = computed(() => props.mode === 'edit');
const categoryOptions = ref<CategoryOption[]>([...props.categories]);
const uomOptions = ref<UomOption[]>([...props.uoms]);
const categoryModalOpen = ref(false);
const categoryModalMode = ref<'create' | 'edit'>('create');
const uomModalOpen = ref(false);
const uomModalMode = ref<'create' | 'edit'>('create');

const categoryMenuValue = computed({
    get: () => (form.category_id === null ? '' : String(form.category_id)),
    set: (value: string) => {
        form.category_id = value === '' ? null : Number(value);
    },
});

const taxConfigMenuValue = computed({
    get: () => (form.tax_config_id === null ? '' : String(form.tax_config_id)),
    set: (value: string) => {
        form.tax_config_id = value === '' ? null : Number(value);
    },
});

const uomMenuValue = computed({
    get: () => form.base_unit,
    set: (value: string) => {
        form.base_unit = value;
    },
});

const categoryLabel = computed(() => {
    if (form.category_id === null) return 'Không chọn';
    return categoryTreeOptions.value.find((category) => category.id === form.category_id)?.fullLabel ?? 'Không chọn';
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

const uomLabel = computed(() => {
    if (form.base_unit.trim() === '') return 'Không chọn';

    const selected = uomOptions.value.find((uom) => uom.name === form.base_unit);
    if (!selected) return form.base_unit;

    return selected.symbol ? `${selected.name} (${selected.symbol})` : selected.name;
});

const taxConfigLabel = computed(() => {
    if (form.tax_config_id === null) return 'Không chọn';
    return props.taxConfigs.find((tax) => tax.id === form.tax_config_id)?.name ?? 'Không chọn';
});

const supplierLabel = computed(() => {
    if (form.supplier_ids.length === 0) return 'Chọn nhà cung cấp';

    if (form.supplier_ids.length === 1) {
        const supplier = props.suppliers.find((item) => item.id === form.supplier_ids[0]);
        return supplier?.name ?? '1 nhà cung cấp';
    }

    return `${form.supplier_ids.length} nhà cung cấp`;
});

const locationTypeLabelMap: Record<WarehouseLocationOption['type'], string> = {
    AREA: 'Khu vực',
    FLOOR: 'Tầng',
    AISLE: 'Lối',
    RACK: 'Kệ',
    SHELF: 'Ngăn',
    BIN: 'Ô',
};

const warehouseLocationsById = computed(() => {
    return new Map<number, WarehouseLocationOption>(
        props.warehouseLocations.map((location) => [location.id, location]),
    );
});

const warehouseLocationOptions = computed(() => {
    return props.warehouseLocations
        .filter((location) => location.is_active !== false)
        .map((location) => {
            const breadcrumbs: string[] = [];
            const visited = new Set<number>();
            let current = location;

            while (current) {
                if (visited.has(current.id)) break;
                visited.add(current.id);

                breadcrumbs.unshift(current.name);

                const parentId = current.parent_id ?? null;
                if (typeof parentId !== 'number') {
                    break;
                }

                const parent = warehouseLocationsById.value.get(parentId);
                if (!parent) {
                    break;
                }

                current = parent;
            }

            const warehouseName = location.warehouse?.name ?? `Kho #${location.warehouse_id}`;
            const typeLabel = locationTypeLabelMap[location.type] ?? location.type;
            const code = String(location.code ?? '').trim();
            const locationCode = code !== '' ? ` (${code})` : '';

            return {
                id: location.id,
                label: `${warehouseName} · ${breadcrumbs.join(' / ')}${locationCode}`,
                shortLabel: `${typeLabel}: ${location.name}${locationCode}`,
                warehouseName,
                warehouseId: location.warehouse_id,
            };
        })
        .sort((left, right) => left.label.localeCompare(right.label, 'vi'));
});

const moneyFormatter = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});

const toNumber = (value: unknown): number | null => {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }

    if (typeof value !== 'string') {
        return null;
    }

    const normalized = value.replaceAll(',', '').trim();
    if (normalized === '') {
        return null;
    }

    const parsed = Number(normalized);
    return Number.isFinite(parsed) ? parsed : null;
};

const formatMoney = (value: unknown): string => {
    const numericValue = toNumber(value);
    if (numericValue === null) {
        return '';
    }

    return moneyFormatter.format(numericValue);
};

const updateMoney = (variant: any, field: 'cost_price' | 'wholesale_price' | 'price', rawValue: string) => {
    const parsed = toNumber(rawValue);
    variant[field] = parsed ?? 0;
};

const updateUnitMoney = (unit: VariantUnitConversion, field: 'cost_price' | 'retail_price' | 'wholesale_price', rawValue: string) => {
    const parsed = toNumber(rawValue);
    unit[field] = parsed ?? 0;
};

const normalizeVariants = (variants: any[]) => {
    if (variants.length === 0) {
        return variants;
    }

    const defaultIndex = variants.findIndex((variant) => variant.is_default === true);
    const targetDefaultIndex = defaultIndex >= 0 ? defaultIndex : 0;
    const orderedVariants = [...variants];

    if (targetDefaultIndex > 0) {
        const [defaultVariant] = orderedVariants.splice(targetDefaultIndex, 1);
        orderedVariants.unshift(defaultVariant);
    }

    return orderedVariants.map((variant, index) => ({
        ...variant,
        is_default: index === 0,
        units: index === 0 ? (variant.units ?? []) : [],
    }));
};

const getDefaultVariant = (): any | null => {
    if (form.variants.length === 0) return null;
    return (form.variants as any[]).find((variant) => variant.is_default === true) ?? (form.variants as any[])[0] ?? null;
};

const buildDefaultVariant = () => ({
    name: props.product?.name ?? '',
    sku: props.product?.sku ?? '',
    barcode: props.product?.barcode ?? '',
    base_unit: props.product?.base_unit ?? '',
    price: 0,
    cost_price: 0,
    wholesale_price: 0,
    discount_price: 0,
    currency: 'VND',
    is_active: true,
    is_default: true,
    units: [] as VariantUnitConversion[],
    storage_assignments: [] as VariantStorageAssignment[],
});

const initialVariants = (() => {
    if ((props.product?.variants?.length ?? 0) > 0) {
        const variants = (props.product?.variants ?? []).map((variant) => {
            const pricesByUom = new Map<number, NonNullable<ExistingVariant['prices']>[number]>();
            for (const price of variant.prices ?? []) {
                pricesByUom.set(price.uom_id, price);
            }

            return {
                ...variant,
                price: toNumber(variant.price) ?? 0,
                cost_price: toNumber(variant.cost_price) ?? 0,
                wholesale_price: toNumber(variant.wholesale_price) ?? 0,
                discount_price: toNumber(variant.discount_price) ?? 0,
                is_default: Boolean(variant.is_default),
                units: (variant.units ?? []).map((unit) => {
                    const unitPrice = typeof unit.uom_id === 'number' ? pricesByUom.get(unit.uom_id) : undefined;

                    return {
                        id: unit.id,
                        uom_id: typeof unit.uom_id === 'number' ? unit.uom_id : null,
                        conversion_factor: Number(unit.conversion_factor ?? 1),
                        cost_price: toNumber(unitPrice?.cost_price ?? unit.cost_price) ?? 0,
                        retail_price: toNumber(unitPrice?.retail_price ?? unit.retail_price) ?? 0,
                        wholesale_price: toNumber(unitPrice?.wholesale_price ?? unit.wholesale_price) ?? 0,
                        currency: String(unitPrice?.currency ?? unit.currency ?? variant.currency ?? 'VND'),
                    };
                }),
                storage_assignments: (variant.product_storage ?? []).map((storage) => ({
                    id: storage.id,
                    warehouse_location_id: Number(storage.warehouse_location_id ?? 0) || null,
                    min_stock_level: Math.max(0, Number(storage.min_stock_level ?? 0) || 0),
                    max_stock_level: Math.max(0, Number(storage.max_stock_level ?? 0) || 0),
                })),
            };
        });

        return normalizeVariants(variants);
    }

    if (props.mode === 'create') {
        return [buildDefaultVariant()];
    }

    return [];
})();

// ── Form ────────────────────────────────────────────────────────────────────

const form = useForm({
    name: props.product?.name ?? '',
    sku: props.product?.sku ?? '',
    barcode: props.product?.barcode ?? '',
    description: props.product?.description ?? '',
    base_unit: props.product?.base_unit ?? '',
    origin: props.product?.origin ?? '',
    brand: props.product?.brand ?? '',
    category_id: props.product?.category_id ?? null,
    tax_config_id: props.product?.tax_config_id ?? null,
    is_active: props.product?.is_active ?? true,
    variants: initialVariants as any[],
    images: (props.product?.images ?? []) as any[],
    supplier_ids: props.supplierIds ?? [],
});

const hasManuallyEditedPrimaryVariantName = ref(false);
const hasManuallyEditedPrimaryVariantSku = ref(false);
const hasManuallyEditedPrimaryVariantBarcode = ref(false);
const previousProductName = ref(form.name);
const previousProductSku = ref(form.sku);
const previousProductBarcode = ref(form.barcode);
const isGeneratingCode = ref(false);

watch(
    () => form.name,
    (newName, oldName) => {
        if (props.mode !== 'create') return;

        if (form.variants.length === 0) {
            form.variants.push(buildDefaultVariant());
        }

        const primaryVariant = form.variants[0];
        if (!primaryVariant) return;

        if (!hasManuallyEditedPrimaryVariantName.value) {
            const trimmedName = newName.trim();
            primaryVariant.name = trimmedName === '' ? '' : trimmedName;
            previousProductName.value = newName;
            return;
        }

        const previousTrimmedName = previousProductName.value.trim();
        if (primaryVariant.name?.trim() === previousTrimmedName && oldName !== newName) {
            const trimmedName = newName.trim();
            primaryVariant.name = trimmedName === '' ? '' : trimmedName;
        }

        previousProductName.value = newName;
    },
);

watch(
    () => form.variants[0]?.name,
    (newName) => {
        if (props.mode !== 'create') return;
        if (typeof newName !== 'string') return;

        const productName = form.name.trim();
        const variantName = newName.trim();

        hasManuallyEditedPrimaryVariantName.value =
            variantName !== '' && variantName !== productName;
    },
);

watch(
    () => form.sku,
    (newSku, oldSku) => {
        if (props.mode !== 'create') return;

        if (form.variants.length === 0) {
            form.variants.push(buildDefaultVariant());
        }

        const primaryVariant = form.variants[0];
        if (!primaryVariant) return;

        if (!hasManuallyEditedPrimaryVariantSku.value) {
            primaryVariant.sku = newSku;
            previousProductSku.value = newSku;
            return;
        }

        if ((primaryVariant.sku ?? '').trim() === previousProductSku.value.trim() && oldSku !== newSku) {
            primaryVariant.sku = newSku;
        }

        previousProductSku.value = newSku;
    },
);

watch(
    () => form.barcode,
    (newBarcode, oldBarcode) => {
        if (props.mode !== 'create') return;

        if (form.variants.length === 0) {
            form.variants.push(buildDefaultVariant());
        }

        const primaryVariant = form.variants[0];
        if (!primaryVariant) return;

        if (!hasManuallyEditedPrimaryVariantBarcode.value) {
            primaryVariant.barcode = newBarcode;
            previousProductBarcode.value = newBarcode;
            return;
        }

        if ((primaryVariant.barcode ?? '').trim() === previousProductBarcode.value.trim() && oldBarcode !== newBarcode) {
            primaryVariant.barcode = newBarcode;
        }

        previousProductBarcode.value = newBarcode;
    },
);

watch(
    () => form.variants[0]?.sku,
    (newSku) => {
        if (props.mode !== 'create') return;
        if (typeof newSku !== 'string') return;

        hasManuallyEditedPrimaryVariantSku.value =
            newSku.trim() !== '' && newSku.trim() !== form.sku.trim();
    },
);

watch(
    () => form.variants[0]?.barcode,
    (newBarcode) => {
        if (props.mode !== 'create') return;
        if (typeof newBarcode !== 'string') return;

        hasManuallyEditedPrimaryVariantBarcode.value =
            newBarcode.trim() !== '' && newBarcode.trim() !== form.barcode.trim();
    },
);

watch(
    () => form.base_unit,
    (newBaseUnit, oldBaseUnit) => {
        const next = newBaseUnit.trim();
        const prev = oldBaseUnit.trim();

        form.variants.forEach((variant: any) => {
            const current = String(variant.base_unit ?? '').trim();
            if (current === '' || current === prev) {
                variant.base_unit = next;
            }
        });
    },
);

const generateCode = async () => {
    const productName = form.name.trim();
    if (productName === '') {
        form.setError('name', 'Vui lòng nhập tên sản phẩm trước khi tạo mã.');
        return;
    }

    isGeneratingCode.value = true;
    form.clearErrors('sku', 'barcode');

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

            form.setError('sku', typeof firstError === 'string' ? firstError : 'Không thể tạo mã tự động.');
            return;
        }

        const payload = await response.json();
        const generatedSku = String(payload?.sku ?? '').trim();
        const generatedBarcode = String(payload?.barcode ?? '').trim();

        if (form.sku.trim() === '' && generatedSku !== '') {
            form.sku = generatedSku;
        }

        if (form.barcode.trim() === '' && generatedBarcode !== '') {
            form.barcode = generatedBarcode;
        }
    } catch {
        form.setError('sku', 'Không thể tạo mã tự động. Vui lòng thử lại.');
    } finally {
        isGeneratingCode.value = false;
    }
};

// ── Image Management ────────────────────────────────────────────────────────

const fileInput = ref<HTMLInputElement | null>(null);
const imagePreviewUrls = ref<Map<string, string>>(new Map());

const triggerFileInput = () => fileInput.value?.click();

const handleFileSelect = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (!input.files) return;

    for (const file of Array.from(input.files)) {
        if (!file.type.startsWith('image/')) continue;
        if (file.size > 5 * 1024 * 1024) continue; // max 5MB

        const previewUrl = URL.createObjectURL(file);
        const key = `new_${Date.now()}_${Math.random().toString(36).slice(2)}`;
        imagePreviewUrls.value.set(key, previewUrl);

        form.images.push({
            _key: key,
            _file: file,
            image_path: null,
            is_default: form.images.length === 0,
        });
    }
    input.value = '';
};

const removeImage = (index: number) => {
    const img = form.images[index];
    if (img._key) {
        const url = imagePreviewUrls.value.get(img._key);
        if (url) URL.revokeObjectURL(url);
        imagePreviewUrls.value.delete(img._key);
    }
    const wasDefault = img.is_default;
    form.images.splice(index, 1);
    if (wasDefault && form.images.length > 0) {
        form.images[0].is_default = true;
    }
};

const setAsPrimary = (index: number) => {
    form.images.forEach((img: any, i: number) => {
        img.is_default = i === index;
    });
};

const getImageSrc = (img: any): string | null => {
    if (img._key) return imagePreviewUrls.value.get(img._key) ?? null;
    return img.image_path ?? null;
};

const primaryImage = computed(() => {
    const found = form.images.find((img: any) => img.is_default);
    return found ?? form.images[0] ?? null;
});

onBeforeUnmount(() => {
    imagePreviewUrls.value.forEach((url) => URL.revokeObjectURL(url));
});

// ── Variant Management ──────────────────────────────────────────────────────

const addVariant = () => {
    const sourceVariant = getDefaultVariant();

    form.variants.push({
        name: sourceVariant?.name ?? (form.variants.length === 0 ? form.name.trim() : ''),
        sku: sourceVariant?.sku ?? '',
        barcode: sourceVariant?.barcode ?? '',
        base_unit: sourceVariant?.base_unit ?? (form.base_unit || ''),
        price: toNumber(sourceVariant?.price) ?? 0,
        cost_price: toNumber(sourceVariant?.cost_price) ?? 0,
        wholesale_price: toNumber(sourceVariant?.wholesale_price) ?? 0,
        discount_price: toNumber(sourceVariant?.discount_price) ?? 0,
        currency: sourceVariant?.currency ?? 'VND',
        is_active: sourceVariant?.is_active ?? true,
        is_default: false,
        units: [],
        storage_assignments: [],
    });
};

const removeVariant = (index: number) => {
    if (index < 0 || index >= form.variants.length) {
        return;
    }

    const variant = form.variants[index] as any;
    form.variants.splice(index, 1);

    if (variant?.is_default === true && form.variants.length > 0) {
        (form.variants[0] as any).is_default = true;
    }

    form.variants = normalizeVariants(form.variants as any[]) as any[];
};

const setDefaultVariant = (index: number) => {
    if (index < 0 || index >= form.variants.length) return;

    form.variants.forEach((variant: any, variantIndex: number) => {
        variant.is_default = variantIndex === index;
    });

    form.variants = normalizeVariants(form.variants as any[]) as any[];
};

const toggleSupplier = (supplierId: number, checked: boolean | 'indeterminate') => {
    if (checked === true) {
        if (!form.supplier_ids.includes(supplierId)) {
            form.supplier_ids = [...form.supplier_ids, supplierId];
        }
        return;
    }

    form.supplier_ids = form.supplier_ids.filter((id: number) => id !== supplierId);
};

const onSupplierCheckedChange = (supplierId: number) => (checked: boolean | 'indeterminate') => {
    toggleSupplier(supplierId, checked);
};

const selectedCategory = computed(() => {
    if (form.category_id === null) return null;
    return categoryOptions.value.find((category) => category.id === form.category_id) ?? null;
});

const openCreateCategoryModal = () => {
    categoryModalMode.value = 'create';
    categoryModalOpen.value = true;
};

const openEditCategoryModal = () => {
    if (selectedCategory.value === null) return;
    categoryModalMode.value = 'edit';
    categoryModalOpen.value = true;
};

const onCategorySaved = (category: CategoryOption) => {
    const index = categoryOptions.value.findIndex((item) => item.id === category.id);

    if (index === -1) {
        categoryOptions.value = [...categoryOptions.value, category].sort((left, right) => left.name.localeCompare(right.name, 'vi'));
    } else {
        categoryOptions.value[index] = category;
        categoryOptions.value = [...categoryOptions.value].sort((left, right) => left.name.localeCompare(right.name, 'vi'));
    }

    form.category_id = category.id;
};

const selectedUom = computed(() => {
    if (form.base_unit.trim() === '') return null;
    return uomOptions.value.find((uom) => uom.name === form.base_unit) ?? null;
});

const openCreateUomModal = () => {
    uomModalMode.value = 'create';
    uomModalOpen.value = true;
};

const openEditUomModal = () => {
    if (selectedUom.value === null) return;
    uomModalMode.value = 'edit';
    uomModalOpen.value = true;
};

const onUomSaved = (uom: UomOption) => {
    const index = uomOptions.value.findIndex((item) => item.id === uom.id);

    if (index === -1) {
        uomOptions.value = [...uomOptions.value, uom].sort((left, right) => left.name.localeCompare(right.name, 'vi'));
    } else {
        uomOptions.value[index] = uom;
        uomOptions.value = [...uomOptions.value].sort((left, right) => left.name.localeCompare(right.name, 'vi'));
    }

    form.base_unit = uom.name;
};

const addVariantConversion = (variant: any) => {
    if (variant?.is_default !== true) {
        return;
    }

    if (!Array.isArray(variant.units)) {
        variant.units = [];
    }

    variant.units.push({
        uom_id: null,
        conversion_factor: 1,
        cost_price: toNumber(variant?.cost_price) ?? 0,
        retail_price: toNumber(variant?.price) ?? 0,
        wholesale_price: toNumber(variant?.wholesale_price) ?? 0,
        currency: String(variant?.currency ?? 'VND'),
    });
};

const removeVariantConversion = (variant: any, index: number) => {
    if (!Array.isArray(variant.units)) {
        return;
    }

    variant.units.splice(index, 1);
};

const availableUomsForVariant = (variant: any, rowIndex: number) => {
    const selectedIds = new Set<number>(
        (variant.units ?? [])
            .map((unit: VariantUnitConversion, idx: number) => idx === rowIndex ? null : unit.uom_id)
            .filter((id: number | null): id is number => typeof id === 'number'),
    );

    return uomOptions.value.filter((uom) => {
        if (selectedIds.has(uom.id)) {
            return false;
        }

        return true;
    });
};

const conversionUomLabel = (unit: VariantUnitConversion, variant: any, rowIndex: number) => {
    if (unit.uom_id === null) {
        return 'Chọn đơn vị';
    }

    const selected = availableUomsForVariant(variant, rowIndex).find((uom) => uom.id === unit.uom_id)
        ?? uomOptions.value.find((uom) => uom.id === unit.uom_id);

    if (!selected) {
        return 'Chọn đơn vị';
    }

    return selected.symbol ? `${selected.name} (${selected.symbol})` : selected.name;
};

const conversionEquationPreview = (unit: VariantUnitConversion, variant: any, rowIndex: number): string => {
    const selectedUomLabel = conversionUomLabel(unit, variant, rowIndex);
    const normalizedFactor = toNumber(unit.conversion_factor) ?? 1;
    const baseUnitLabel = String(variant?.base_unit ?? form.base_unit ?? '').trim() || 'đơn vị cơ bản';

    if (selectedUomLabel === 'Chọn đơn vị') {
        return `1 đơn vị quy đổi = ${normalizedFactor} ${baseUnitLabel}`;
    }

    return `1 ${selectedUomLabel} = ${normalizedFactor} ${baseUnitLabel}`;
};

const addVariantStorageAssignment = (variant: any) => {
    if (!Array.isArray(variant.storage_assignments)) {
        variant.storage_assignments = [];
    }

    variant.storage_assignments.push({
        warehouse_location_id: null,
        min_stock_level: 0,
        max_stock_level: 0,
    });
};

const removeVariantStorageAssignment = (variant: any, index: number) => {
    if (!Array.isArray(variant.storage_assignments)) {
        return;
    }

    variant.storage_assignments.splice(index, 1);
};

const availableStorageLocationsForVariant = (variant: any, rowIndex: number) => {
    const selectedIds = new Set<number>(
        (variant.storage_assignments ?? [])
            .map((assignment: VariantStorageAssignment, idx: number) => idx === rowIndex ? null : assignment.warehouse_location_id)
            .filter((id: number | null): id is number => typeof id === 'number'),
    );

    return warehouseLocationOptions.value.filter((location) => !selectedIds.has(location.id));
};

const storageLocationLabel = (assignment: VariantStorageAssignment, variant: any, rowIndex: number) => {
    if (assignment.warehouse_location_id === null) {
        return 'Chọn vị trí lưu kho';
    }

    const selected = availableStorageLocationsForVariant(variant, rowIndex)
        .find((location) => location.id === assignment.warehouse_location_id)
        ?? warehouseLocationOptions.value.find((location) => location.id === assignment.warehouse_location_id);

    if (!selected) {
        return 'Chọn vị trí lưu kho';
    }

    return selected.label;
};

const isMeaningfulVariant = (variant: any): boolean => {
    const name = String(variant?.name ?? '').trim();
    const sku = String(variant?.sku ?? '').trim();
    const barcode = String(variant?.barcode ?? '').trim();

    return name !== '' || sku !== '' || barcode !== '';
};

const variantErrors = computed(() => {
    const entries = Object.entries(form.errors ?? {})
        .filter(([key]) => key.startsWith('variants.'))
        .map(([key, message]) => `${key.replace(/^variants\./, 'Biến thể ')}: ${message}`);

    return Array.from(new Set(entries));
});

// ── Submit ───────────────────────────────────────────────────────────────────

const submit = () => {
    const rawVariants = (form.variants as any[]);
    const sourceVariants = rawVariants.filter((variant) => isMeaningfulVariant(variant));
    const effectiveVariants = sourceVariants.length > 0 ? sourceVariants : rawVariants;

    const missingBaseUnitIndexes: number[] = [];

    const normalizedVariants = normalizeVariants(
        effectiveVariants.map((variant, index) => {
            const normalizedSku = String(variant?.sku ?? '').trim();
            const normalizedBarcode = String(variant?.barcode ?? '').trim();
            const normalizedBaseUnit = String(variant?.base_unit ?? form.base_unit ?? '').trim();

            let sku = normalizedSku;
            let barcode = normalizedBarcode;

            if (sku === '' && barcode === '') {
                if (String(form.sku ?? '').trim() !== '') {
                    sku = index === 0 ? String(form.sku).trim() : `${String(form.sku).trim()}-${index + 1}`;
                } else if (String(form.barcode ?? '').trim() !== '') {
                    barcode = index === 0 ? String(form.barcode).trim() : `${String(form.barcode).trim()}-${index + 1}`;
                }
            }

            if (normalizedBaseUnit === '') {
                missingBaseUnitIndexes.push(index);
            }

            return {
                ...variant,
                sku,
                barcode,
                base_unit: normalizedBaseUnit,
                price: toNumber(variant.price) ?? 0,
                cost_price: toNumber(variant.cost_price) ?? 0,
                wholesale_price: toNumber(variant.wholesale_price) ?? 0,
                discount_price: toNumber(variant.discount_price),
                units: variant.is_default === true
                    ? (variant.units ?? []).map((unit: VariantUnitConversion) => ({
                        ...unit,
                        cost_price: toNumber(unit.cost_price) ?? 0,
                        retail_price: toNumber(unit.retail_price) ?? 0,
                        wholesale_price: toNumber(unit.wholesale_price) ?? 0,
                        currency: String(unit.currency ?? variant.currency ?? 'VND'),
                    }))
                    : [],
                storage_assignments: (variant.storage_assignments ?? [])
                    .map((assignment: VariantStorageAssignment) => {
                        const warehouseLocationId = assignment.warehouse_location_id === null
                            ? null
                            : Number(assignment.warehouse_location_id);
                        const minStockLevel = Math.max(0, Number(assignment.min_stock_level ?? 0) || 0);
                        const maxStockLevel = Math.max(0, Number(assignment.max_stock_level ?? 0) || 0);

                        return {
                            id: assignment.id,
                            warehouse_location_id: Number.isFinite(warehouseLocationId ?? NaN) ? warehouseLocationId : null,
                            min_stock_level: minStockLevel,
                            max_stock_level: maxStockLevel,
                        };
                    })
                    .filter((assignment: { warehouse_location_id: number | null }) => assignment.warehouse_location_id !== null),
            };
        }),
    );

    if (missingBaseUnitIndexes.length > 0) {
        const message = `Thiếu đơn vị tính cho biến thể: ${missingBaseUnitIndexes.map((index) => index + 1).join(', ')}`;
        form.setError('base_unit', message);
        return;
    }

    form.clearErrors('base_unit');

    form.transform((data) => ({
        ...data,
        variants: normalizedVariants,
    }));

    const hasNewImageUpload = (form.images as any[]).some((image) => image?._file instanceof File);

    if (props.mode === 'create') {
        form.post(tenantRoutes.products.store(), {
            forceFormData: hasNewImageUpload,
        });
        return;
    }

    if (hasNewImageUpload) {
        form.transform((data) => ({
            ...data,
            variants: normalizedVariants,
            _method: 'put',
        }));

        form.post(tenantRoutes.products.update(productPublicRef(props.product!)), {
            forceFormData: true,
        });

        return;
    }

    form.put(tenantRoutes.products.update(productPublicRef(props.product!)), {
    });
};
</script>

<template>
    <Head :title="isEdit ? 'Sửa sản phẩm' : 'Thêm sản phẩm'" />
    <TenantLayout>
        <div class="mx-auto max-w-screen-2xl space-y-6">
            <!-- ── Header ────────────────────────────────────────────── -->
            <div class="flex items-center justify-between rounded-2xl border border-border/70 bg-card/95 p-5 shadow-sm backdrop-blur-sm">
                <div class="flex items-center gap-3">
                    <Link
                        :href="tenantRoutes.products.index()"
                        class="rounded-xl border border-border/70 bg-background p-2 transition hover:bg-muted/60"
                    >
                        <ArrowLeft class="size-5" />
                    </Link>
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">
                            {{ isEdit ? 'Sửa sản phẩm' : 'Thêm sản phẩm mới' }}
                        </h1>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            {{ isEdit ? `SKU: ${product?.sku || '-'}` : 'Điền thông tin sản phẩm bên dưới' }}
                        </p>
                    </div>
                    <div class="flex justify-start gap-2">
                        <span
                            class="rounded-full px-3 py-1 text-xs font-semibold"
                            :class="form.is_active ? 'bg-primary/15 text-primary' : 'bg-muted text-muted-foreground'"
                        >
                            {{ form.is_active ? 'Đang bán' : 'Ngừng bán' }}
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:opacity-50"
                        @click="submit"
                    >
                        <Save class="size-4" />
                        {{ isEdit ? 'Cập nhật' : 'Tạo sản phẩm' }}
                    </button>
                    <Link
                        :href="tenantRoutes.products.index()"
                        class="inline-flex items-center gap-2 rounded-xl border border-border/70 bg-background px-5 py-2.5 text-sm transition hover:bg-muted/60"
                    >
                        Hủy
                    </Link>
                </div>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <!-- ── 3-Column Layout ───────────────────────────────── -->
                <div class="grid gap-6 lg:grid-cols-[280px_1fr_300px]">
                    <!-- ═══ LEFT: Images ═══════════════════════════════ -->
                    <div class="space-y-4">
                        <div class="rounded-2xl border border-border/70 bg-card p-5">
                            <div class="mb-4 flex items-center justify-between">
                                <h2 class="text-sm font-semibold">Hình ảnh</h2>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground transition hover:opacity-90"
                                    @click="triggerFileInput"
                                >
                                    <ImagePlus class="size-3.5" />
                                    Tải lên
                                </button>
                            </div>

                            <input
                                ref="fileInput"
                                type="file"
                                accept="image/*"
                                multiple
                                class="hidden"
                                @change="handleFileSelect"
                            />

                            <!-- Primary image preview -->
                            <div class="aspect-square overflow-hidden rounded-xl border border-border/70 bg-muted/40">
                                <img
                                    v-if="primaryImage && getImageSrc(primaryImage)"
                                    :src="getImageSrc(primaryImage)!"
                                    :alt="form.name || 'Ảnh chính'"
                                    class="size-full object-cover"
                                />
                                <div v-else class="flex size-full flex-col items-center justify-center text-muted-foreground">
                                    <ImagePlus class="mb-2 size-10" />
                                    <p class="text-xs">Chưa có ảnh</p>
                                </div>
                            </div>

                            <!-- Thumbnail grid -->
                            <div v-if="form.images.length > 0" class="mt-3">
                                <p class="mb-2 text-xs text-muted-foreground">Tất cả ảnh ({{ form.images.length }})</p>
                                <div class="grid grid-cols-4 gap-1.5">
                                    <div
                                        v-for="(img, index) in form.images"
                                        :key="img.id ?? img._key"
                                        class="group relative aspect-square overflow-hidden rounded-lg border border-border/70 bg-muted/40"
                                    >
                                        <img
                                            v-if="getImageSrc(img)"
                                            :src="getImageSrc(img)!"
                                            class="size-full object-cover"
                                        />

                                        <!-- Overlay actions -->
                                        <div class="absolute inset-0 flex items-center justify-center gap-1 bg-black/0 opacity-0 transition-all group-hover:bg-black/40 group-hover:opacity-100">
                                            <button
                                                v-if="!img.is_default"
                                                type="button"
                                                class="rounded-full bg-primary p-1 text-primary-foreground hover:opacity-90"
                                                title="Đặt làm ảnh chính"
                                                @click="setAsPrimary(index)"
                                            >
                                                <Star class="size-3" />
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-full bg-destructive p-1 text-destructive-foreground hover:opacity-90"
                                                title="Xóa ảnh"
                                                @click="removeImage(index)"
                                            >
                                                <X class="size-3" />
                                            </button>
                                        </div>

                                        <!-- Primary badge -->
                                        <div
                                            v-if="img.is_default"
                                            class="absolute left-0.5 top-0.5 rounded bg-primary px-1 py-0.5 text-[9px] font-bold leading-none text-primary-foreground"
                                        >
                                            Chính
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Upload hint -->
                            <div
                                v-if="form.images.length === 0"
                                class="mt-3 rounded-lg border-2 border-dashed border-border p-4 text-center"
                            >
                                <p class="text-xs text-muted-foreground">Kéo thả hoặc bấm "Tải lên"</p>
                                <p class="text-[10px] text-muted-foreground">JPG, PNG, max 5MB</p>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="rounded-2xl border border-border/70 bg-card p-5">
                            <h2 class="mb-3 text-sm font-semibold">Trạng thái</h2>
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input v-model="form.is_active" type="checkbox" class="rounded" />
                                Sản phẩm đang bán
                            </label>
                            <p v-if="form.errors.is_active" class="mt-1 text-xs text-red-600">{{ form.errors.is_active }}</p>
                        </div>
                    </div>

                    <!-- ═══ CENTER: Basic Info ═════════════════════════ -->
                    <div class="space-y-5">
                        <!-- Basic information -->
                        <div class="rounded-2xl border border-border/70 bg-card p-5">
                            <h2 class="mb-4 text-sm font-semibold">Thông tin cơ bản</h2>
                            <div class="space-y-4">
                                <!-- Name -->
                                <div>
                                    <label class="mb-1 block text-sm font-medium">Tên sản phẩm <span class="text-destructive">*</span></label>
                                    <input v-model="form.name" placeholder="Nhập tên sản phẩm" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring" />
                                    <p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
                                </div>

                                <!-- SKU + Barcode -->
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium">SKU</label>
                                        <input v-model="form.sku" placeholder="Mã SKU" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 font-mono text-sm outline-none transition focus:ring-2 focus:ring-ring" />
                                        <p v-if="form.errors.sku" class="mt-1 text-xs text-destructive">{{ form.errors.sku }}</p>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium">Barcode</label>
                                        <input v-model="form.barcode" placeholder="Mã vạch" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 font-mono text-sm outline-none transition focus:ring-2 focus:ring-ring" />
                                        <p v-if="form.errors.barcode" class="mt-1 text-xs text-destructive">{{ form.errors.barcode }}</p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-start">
                                    <GenerateProductCode
                                        :loading="isGeneratingCode"
                                        :disabled="form.name.trim() === ''"
                                        @generate="generateCode"
                                    />
                                </div>

                                <!-- Unit + Brand -->
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium">Đơn vị tính <span class="text-destructive">*</span></label>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <button type="button" class="inline-flex w-full items-center justify-between rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring">
                                                    <span class="truncate">{{ uomLabel }}</span>
                                                    <ChevronDown class="ml-2 size-4 text-muted-foreground" />
                                                </button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-56">
                                                <DropdownMenuLabel>Đơn vị tính</DropdownMenuLabel>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuRadioGroup v-model="uomMenuValue">
                                                    <DropdownMenuRadioItem value="">Không chọn</DropdownMenuRadioItem>
                                                    <DropdownMenuRadioItem v-for="u in uomOptions" :key="u.id" :value="u.name">
                                                        {{ u.symbol ? `${u.name} (${u.symbol})` : u.name }}
                                                    </DropdownMenuRadioItem>
                                                </DropdownMenuRadioGroup>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                        <p v-if="form.errors.base_unit" class="mt-1 text-xs text-destructive">{{ form.errors.base_unit }}</p>
                                        <div class="mt-2 flex gap-2">
                                            <button type="button" class="rounded-lg border border-border/70 bg-background px-2.5 py-1.5 text-xs transition hover:bg-muted/60" @click="openCreateUomModal">
                                                <Plus class="size-3" />
                                            </button>
                                            <button type="button" class="rounded-lg border border-border/70 bg-background px-2.5 py-1.5 text-xs transition hover:bg-muted/60 disabled:cursor-not-allowed disabled:opacity-50" :disabled="selectedUom === null" @click="openEditUomModal">
                                                <Pencil class="size-3" />
                                            </button>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium">Thương hiệu</label>
                                        <input v-model="form.brand" placeholder="Thương hiệu" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring" />
                                        <p v-if="form.errors.brand" class="mt-1 text-xs text-destructive">{{ form.errors.brand }}</p>
                                    </div>
                                </div>

                                <!-- Origin -->
                                <div>
                                    <label class="mb-1 block text-sm font-medium">Xuất xứ</label>
                                    <input v-model="form.origin" placeholder="Quốc gia / nơi sản xuất" class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring" />
                                    <p v-if="form.errors.origin" class="mt-1 text-xs text-destructive">{{ form.errors.origin }}</p>
                                </div>

                                <!-- Description -->
                                <div>
                                    <label class="mb-1 block text-sm font-medium">Mô tả</label>
                                    <textarea v-model="form.description" rows="3" placeholder="Mô tả sản phẩm (tùy chọn)" class="w-full resize-none rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring" />
                                    <p v-if="form.errors.description" class="mt-1 text-xs text-destructive">{{ form.errors.description }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Variants -->
                        <div class="rounded-2xl border border-border/70 bg-card p-5">
                            <div class="mb-4 flex items-center justify-between">
                                <div>
                                    <h2 class="text-sm font-semibold">Biến thể sản phẩm</h2>
                                    <p class="text-[11px] text-muted-foreground">Luôn có 1 biến thể gốc kế thừa từ sản phẩm, bạn có thể thêm biến thể khác nếu cần.</p>
                                </div>
                                <button
                                    type="button"
                                    class="rounded-lg border border-border/70 bg-background px-3 py-1.5 text-xs font-medium transition hover:bg-muted/60"
                                    @click="addVariant"
                                >
                                    + Thêm biến thể
                                </button>
                            </div>

                            <div v-if="variantErrors.length > 0" class="mb-3 rounded-lg border border-destructive/40 bg-destructive/5 p-2">
                                <p class="mb-1 text-xs font-medium text-destructive">Lỗi dữ liệu biến thể:</p>
                                <ul class="space-y-0.5 text-[11px] text-destructive">
                                    <li v-for="error in variantErrors" :key="error">- {{ error }}</li>
                                </ul>
                            </div>

                            <div v-if="form.variants.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                                Chưa có biến thể nào. Thêm biến thể để quản lý giá và SKU riêng.
                            </div>

                            <div v-else class="space-y-3">
                                <div
                                    v-for="(variant, vi) in form.variants"
                                    :key="variant.id ?? vi"
                                    class="rounded-xl border border-border/70 bg-background/50 p-4"
                                >
                                    <div class="mb-3 flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-semibold text-muted-foreground">
                                                {{ variant.is_default ? 'Biến thể gốc' : `Biến thể ${vi + 1}` }}
                                            </span>
                                            <span
                                                v-if="variant.is_default"
                                                class="rounded-full bg-primary/15 px-2 py-0.5 text-[10px] font-semibold text-primary"
                                            >
                                                Mặc định
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button
                                                v-if="!variant.is_default"
                                                type="button"
                                                class="rounded-md border border-border/70 bg-background px-2 py-1 text-[11px] transition hover:bg-muted/60"
                                                @click="setDefaultVariant(vi)"
                                            >
                                                Đặt mặc định
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded p-1 text-destructive hover:bg-destructive/10"
                                                @click="removeVariant(vi)"
                                            >
                                                <Trash2 class="size-3.5" />
                                            </button>
                                        </div>
                                    </div>

                                    <div class="grid gap-3 md:grid-cols-3">
                                        <div>
                                            <label class="mb-1 block text-xs font-medium">Tên</label>
                                            <input v-model="variant.name" placeholder="VD: Đỏ - Size L" class="w-full rounded-lg border border-border/70 bg-background px-2.5 py-1.5 text-sm outline-none transition focus:ring-2 focus:ring-ring" />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-medium">SKU</label>
                                            <input v-model="variant.sku" placeholder="SKU biến thể" class="w-full rounded-lg border border-border/70 bg-background px-2.5 py-1.5 font-mono text-sm outline-none transition focus:ring-2 focus:ring-ring" />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-medium">Barcode</label>
                                            <input v-model="variant.barcode" placeholder="Barcode biến thể" class="w-full rounded-lg border border-border/70 bg-background px-2.5 py-1.5 font-mono text-sm outline-none transition focus:ring-2 focus:ring-ring" />
                                        </div>
                                    </div>

                                    <div class="mt-3 grid gap-3 md:grid-cols-3">
                                        <div>
                                            <label class="mb-1 block text-xs font-medium">Giá vốn</label>
                                            <input
                                                :value="formatMoney(variant.cost_price)"
                                                type="text"
                                                inputmode="decimal"
                                                class="w-full rounded-lg border border-border/70 bg-background px-2.5 py-1.5 text-right text-sm outline-none transition focus:ring-2 focus:ring-ring"
                                                @input="(event) => updateMoney(variant, 'cost_price', (event.target as HTMLInputElement).value)"
                                            />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-medium">Giá sỉ</label>
                                            <input
                                                :value="formatMoney(variant.wholesale_price)"
                                                type="text"
                                                inputmode="decimal"
                                                class="w-full rounded-lg border border-border/70 bg-background px-2.5 py-1.5 text-right text-sm outline-none transition focus:ring-2 focus:ring-ring"
                                                @input="(event) => updateMoney(variant, 'wholesale_price', (event.target as HTMLInputElement).value)"
                                            />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-medium">Giá lẻ <span class="text-destructive">*</span></label>
                                            <input
                                                :value="formatMoney(variant.price)"
                                                type="text"
                                                inputmode="decimal"
                                                class="w-full rounded-lg border border-border/70 bg-background px-2.5 py-1.5 text-right text-sm outline-none transition focus:ring-2 focus:ring-ring"
                                                @input="(event) => updateMoney(variant, 'price', (event.target as HTMLInputElement).value)"
                                            />
                                        </div>
                                    </div>

                                    <div class="mt-3 flex items-center gap-4">
                                        <label class="inline-flex items-center gap-1.5 text-xs">
                                            <input v-model="variant.is_active" type="checkbox" class="rounded" />
                                            Kích hoạt
                                        </label>
                                    </div>

                                    <div class="mt-3 rounded-lg border border-border/70 bg-card/60 p-3">
                                        <div class="mb-2 flex items-center justify-between">
                                            <p class="text-xs font-semibold">Liên kết quy đổi theo sản phẩm</p>
                                            <button
                                                v-if="variant.is_default"
                                                type="button"
                                                class="rounded-md border border-border/70 bg-background px-2 py-1 text-[11px] transition hover:bg-muted/60"
                                                @click="addVariantConversion(variant)"
                                            >
                                                + Thêm quy đổi
                                            </button>
                                        </div>

                                        <div v-if="variant.is_default && (!variant.units || variant.units.length === 0)" class="text-[11px] text-muted-foreground">
                                            Chưa có quy đổi. Ví dụ: 1 Thùng = 24 Lon.
                                        </div>

                                        <div v-else-if="variant.is_default" class="space-y-2">
                                            <div
                                                v-for="(unit, ui) in variant.units"
                                                :key="unit.id ?? `new-${vi}-${ui}`"
                                                class="space-y-2 rounded-md border border-border/60 bg-background/70 p-2"
                                            >
                                                <div class="grid grid-cols-[1fr_120px_auto] items-center gap-2">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger as-child>
                                                            <button type="button" class="inline-flex w-full items-center justify-between rounded-lg border border-border/70 bg-background px-2.5 py-1.5 text-xs outline-none transition focus:ring-2 focus:ring-ring">
                                                                <span class="truncate">{{ conversionUomLabel(unit, variant, Number(ui)) }}</span>
                                                                <ChevronDown class="ml-2 size-3.5 text-muted-foreground" />
                                                            </button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-56">
                                                            <DropdownMenuLabel>Đơn vị quy đổi</DropdownMenuLabel>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuRadioGroup
                                                                :model-value="unit.uom_id === null ? '' : String(unit.uom_id)"
                                                                @update:model-value="(value) => { unit.uom_id = value === '' ? null : Number(value); }"
                                                            >
                                                                <DropdownMenuRadioItem value="">Chọn đơn vị</DropdownMenuRadioItem>
                                                                <DropdownMenuRadioItem
                                                                    v-for="u in availableUomsForVariant(variant, Number(ui))"
                                                                    :key="u.id"
                                                                    :value="String(u.id)"
                                                                >
                                                                    {{ u.symbol ? `${u.name} (${u.symbol})` : u.name }}
                                                                </DropdownMenuRadioItem>
                                                            </DropdownMenuRadioGroup>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>

                                                    <input
                                                        v-model.number="unit.conversion_factor"
                                                        type="number"
                                                        min="1"
                                                        step="0.000001"
                                                        placeholder="Hệ số"
                                                        class="w-full rounded-lg border border-border/70 bg-background px-2.5 py-1.5 text-right text-xs outline-none transition focus:ring-2 focus:ring-ring"
                                                    />

                                                    <button
                                                        type="button"
                                                        class="rounded p-1 text-destructive hover:bg-destructive/10"
                                                        @click="removeVariantConversion(variant, Number(ui))"
                                                    >
                                                        <Trash2 class="size-3.5" />
                                                    </button>
                                                </div>

                                                <div class="grid gap-2 md:grid-cols-3">
                                                    <input
                                                        :value="formatMoney(unit.cost_price)"
                                                        type="text"
                                                        inputmode="decimal"
                                                        placeholder="Giá vốn"
                                                        class="w-full rounded-lg border border-border/70 bg-background px-2.5 py-1.5 text-right text-xs outline-none transition focus:ring-2 focus:ring-ring"
                                                        @input="(event) => updateUnitMoney(unit, 'cost_price', (event.target as HTMLInputElement).value)"
                                                    />
                                                    <input
                                                        :value="formatMoney(unit.wholesale_price)"
                                                        type="text"
                                                        inputmode="decimal"
                                                        placeholder="Giá sỉ"
                                                        class="w-full rounded-lg border border-border/70 bg-background px-2.5 py-1.5 text-right text-xs outline-none transition focus:ring-2 focus:ring-ring"
                                                        @input="(event) => updateUnitMoney(unit, 'wholesale_price', (event.target as HTMLInputElement).value)"
                                                    />
                                                    <input
                                                        :value="formatMoney(unit.retail_price)"
                                                        type="text"
                                                        inputmode="decimal"
                                                        placeholder="Giá lẻ"
                                                        class="w-full rounded-lg border border-border/70 bg-background px-2.5 py-1.5 text-right text-xs outline-none transition focus:ring-2 focus:ring-ring"
                                                        @input="(event) => updateUnitMoney(unit, 'retail_price', (event.target as HTMLInputElement).value)"
                                                    />
                                                </div>

                                                <p class="text-[11px] text-muted-foreground">
                                                    {{ conversionEquationPreview(unit, variant, Number(ui)) }}
                                                </p>
                                            </div>
                                        </div>

                                        <div v-else class="text-[11px] text-muted-foreground">
                                            Biến thể riêng quản lý SKU/Barcode và tồn kho độc lập, không áp dụng quy đổi UOM tại đây.
                                        </div>

                                        <p v-if="variant.is_default" class="mt-2 text-[11px] text-muted-foreground">
                                            Hệ số được hiểu: 1 đơn vị quy đổi = N đơn vị cơ bản của sản phẩm.
                                        </p>
                                    </div>

                                    <div class="mt-3 rounded-lg border border-border/70 bg-card/60 p-3">
                                        <div class="mb-2 flex items-center justify-between">
                                            <p class="text-xs font-semibold">Vị trí cất trữ theo kho / tầng / kệ</p>
                                            <button
                                                type="button"
                                                class="rounded-md border border-border/70 bg-background px-2 py-1 text-[11px] transition hover:bg-muted/60"
                                                @click="addVariantStorageAssignment(variant)"
                                            >
                                                + Thêm vị trí
                                            </button>
                                        </div>

                                        <div v-if="!warehouseLocationOptions.length" class="text-[11px] text-muted-foreground">
                                            Chưa có vị trí kho khả dụng. Vui lòng cấu hình vị trí kho trước.
                                        </div>

                                        <div v-else-if="!variant.storage_assignments || variant.storage_assignments.length === 0" class="text-[11px] text-muted-foreground">
                                            Chưa gán vị trí lưu kho cho biến thể này.
                                        </div>

                                        <div v-else class="space-y-2">
                                            <div
                                                v-for="(assignment, si) in variant.storage_assignments"
                                                :key="assignment.id ?? `storage-${vi}-${si}`"
                                                class="space-y-2 rounded-md border border-border/60 bg-background/70 p-2"
                                            >
                                                <div class="grid grid-cols-[1fr_90px_90px_auto] items-center gap-2">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger as-child>
                                                            <button type="button" class="inline-flex w-full items-center justify-between rounded-lg border border-border/70 bg-background px-2.5 py-1.5 text-xs outline-none transition focus:ring-2 focus:ring-ring">
                                                                <span class="truncate">{{ storageLocationLabel(assignment, variant, Number(si)) }}</span>
                                                                <ChevronDown class="ml-2 size-3.5 text-muted-foreground" />
                                                            </button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-80 max-w-[32rem]">
                                                            <DropdownMenuLabel>Vị trí lưu kho</DropdownMenuLabel>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuRadioGroup
                                                                :model-value="assignment.warehouse_location_id === null ? '' : String(assignment.warehouse_location_id)"
                                                                @update:model-value="(value) => { assignment.warehouse_location_id = value === '' ? null : Number(value); }"
                                                            >
                                                                <DropdownMenuRadioItem value="">Chọn vị trí</DropdownMenuRadioItem>
                                                                <DropdownMenuRadioItem
                                                                    v-for="location in availableStorageLocationsForVariant(variant, Number(si))"
                                                                    :key="location.id"
                                                                    :value="String(location.id)"
                                                                >
                                                                    {{ location.label }}
                                                                </DropdownMenuRadioItem>
                                                            </DropdownMenuRadioGroup>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>

                                                    <input
                                                        v-model.number="assignment.min_stock_level"
                                                        type="number"
                                                        min="0"
                                                        step="1"
                                                        placeholder="Min"
                                                        class="w-full rounded-lg border border-border/70 bg-background px-2.5 py-1.5 text-right text-xs outline-none transition focus:ring-2 focus:ring-ring"
                                                    />

                                                    <input
                                                        v-model.number="assignment.max_stock_level"
                                                        type="number"
                                                        min="0"
                                                        step="1"
                                                        placeholder="Max"
                                                        class="w-full rounded-lg border border-border/70 bg-background px-2.5 py-1.5 text-right text-xs outline-none transition focus:ring-2 focus:ring-ring"
                                                    />

                                                    <button
                                                        type="button"
                                                        class="rounded p-1 text-destructive hover:bg-destructive/10"
                                                        @click="removeVariantStorageAssignment(variant, Number(si))"
                                                    >
                                                        <Trash2 class="size-3.5" />
                                                    </button>
                                                </div>

                                                <div class="grid grid-cols-2 gap-2 text-[11px] text-muted-foreground">
                                                    <p>
                                                        Tồn tối thiểu: {{ Number(assignment.min_stock_level || 0) }}
                                                    </p>
                                                    <p class="text-right">
                                                        Tồn tối đa: {{ Number(assignment.max_stock_level || 0) }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ RIGHT: Classification & Relations ═════════ -->
                    <div class="space-y-4">
                        <!-- Category & Tax -->
                        <div class="rounded-2xl border border-border/70 bg-card p-5">
                            <h2 class="mb-4 text-sm font-semibold">Phân loại</h2>

                            <div class="space-y-4">
                                <div>
                                    <label class="mb-1 block text-xs font-medium">Danh mục</label>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <button type="button" class="inline-flex w-full items-center justify-between rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring">
                                                <span class="truncate">{{ categoryLabel }}</span>
                                                <ChevronDown class="ml-2 size-4 text-muted-foreground" />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-56">
                                            <DropdownMenuLabel>Danh mục</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuRadioGroup v-model="categoryMenuValue">
                                                <DropdownMenuRadioItem value="">Không chọn</DropdownMenuRadioItem>
                                                <DropdownMenuRadioItem v-for="c in categoryTreeOptions" :key="c.id" :value="String(c.id)">
                                                    {{ c.treeLabel }}
                                                </DropdownMenuRadioItem>
                                            </DropdownMenuRadioGroup>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                    <p v-if="form.errors.category_id" class="mt-1 text-xs text-destructive">{{ form.errors.category_id }}</p>
                                    <div class="mt-2 flex gap-2">
                                        <button type="button" class="rounded-lg border border-border/70 bg-background px-2.5 py-1.5 text-xs transition hover:bg-muted/60" @click="openCreateCategoryModal">
                                            <Plus class="size-3" />
                                        </button>
                                        <button type="button" class="rounded-lg border border-border/70 bg-background px-2.5 py-1.5 text-xs transition hover:bg-muted/60 disabled:cursor-not-allowed disabled:opacity-50" :disabled="selectedCategory === null" @click="openEditCategoryModal">
                                            <Pencil class="size-3" />
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-medium">Cấu hình thuế</label>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <button type="button" class="inline-flex w-full items-center justify-between rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring">
                                                <span class="truncate">{{ taxConfigLabel }}</span>
                                                <ChevronDown class="ml-2 size-4 text-muted-foreground" />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-56">
                                            <DropdownMenuLabel>Cấu hình thuế</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuRadioGroup v-model="taxConfigMenuValue">
                                                <DropdownMenuRadioItem value="">Không chọn</DropdownMenuRadioItem>
                                                <DropdownMenuRadioItem v-for="t in props.taxConfigs" :key="t.id" :value="String(t.id)">
                                                    {{ t.name }}
                                                </DropdownMenuRadioItem>
                                            </DropdownMenuRadioGroup>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                    <p v-if="form.errors.tax_config_id" class="mt-1 text-xs text-destructive">{{ form.errors.tax_config_id }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Suppliers -->
                        <div class="rounded-2xl border border-border/70 bg-card p-5">
                            <h2 class="mb-4 text-sm font-semibold">Nhà cung cấp</h2>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <button type="button" class="inline-flex w-full items-center justify-between rounded-xl border border-border/70 bg-background px-3 py-2 text-sm outline-none transition focus:ring-2 focus:ring-ring">
                                        <span class="truncate">{{ supplierLabel }}</span>
                                        <ChevronDown class="ml-2 size-4 text-muted-foreground" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-64">
                                    <DropdownMenuLabel>Nhà cung cấp</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <div class="max-h-56 overflow-auto">
                                        <DropdownMenuCheckboxItem
                                            v-for="s in props.suppliers"
                                            :key="s.id"
                                            :checked="form.supplier_ids.includes(s.id)"
                                            @update:checked="onSupplierCheckedChange(s.id)"
                                        >
                                            {{ s.name }}
                                        </DropdownMenuCheckboxItem>
                                    </div>
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <p class="mt-1.5 text-[11px] text-muted-foreground">Chọn một hoặc nhiều nhà cung cấp.</p>
                            <p v-if="form.errors.supplier_ids" class="mt-1 text-xs text-destructive">{{ form.errors.supplier_ids }}</p>
                        </div>
                    </div>
                </div>
            </form>

            <CategoryFormModal
                v-model:open="categoryModalOpen"
                :mode="categoryModalMode"
                :category="categoryModalMode === 'edit' ? selectedCategory : null"
                :categories="categoryOptions"
                @saved="onCategorySaved"
            />

            <UOMFormModal
                v-model:open="uomModalOpen"
                :mode="uomModalMode"
                :uom="uomModalMode === 'edit' ? selectedUom : null"
                :uoms="uomOptions"
                @saved="onUomSaved"
            />
        </div>
    </TenantLayout>
</template>
