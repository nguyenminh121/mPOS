<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, ChevronDown, Info, Minus, MoreVertical, Plus, Search, ShoppingCart, Trash2, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import DateTimePicker from '@/components/DateTimePicker.vue';
import PosTenantHeader from '@/components/PosTenantHeader.vue';
import CustomerFormModal from '@/components/tenant/customers/modals/CustomerFormModal.vue';
import AddProductModal from '@/components/tenant/products/modals/AddProductModal.vue';
import ProductInfoModal from '@/components/tenant/products/modals/ProductInfoModal.vue';
import ProductPriceHistoryModal from '@/components/tenant/sales/modals/ProductPriceHistoryModal.vue';
import SelectOrderModal from '@/components/tenant/sales/modals/SelectOrderModal.vue';
import SuccessModal from '@/components/tenant/sales/modals/SuccessModal.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
    DropdownMenuItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { createTabId, toDatetimeLocal } from '@/composables/pos/core/tab';
import { usePosCart } from '@/composables/pos/features/usePosCart';
import { usePosCatalogActions } from '@/composables/pos/features/usePosCatalogActions';
import { usePosPrice } from '@/composables/pos/features/usePosPrice';
import { usePosTabs } from '@/composables/pos/features/usePosTabs';
import type {
    CartItem,
    CategoryOption,
    CustomerOption,
    PosDraftTab,
    PriceListOption,
    SaleOption,
    SellerOption,
    SurchargeOption,
    UomOption,
    VariantOption,
    WarehouseOption,
} from '@/composables/pos/models';
import { usePos } from '@/composables/pos/usePos';
import { useAdaptiveSync } from '@/composables/sync/useAdaptiveSync';
import { useVisibilitySync } from '@/composables/sync/useVisibilitySync';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { tenantRoutes } from '@/lib/tenantRoutes';

const POS_DRAFT_STORAGE_KEY = 'tenant-pos-drafts-v1';

const props = defineProps<{
    sales: { data: SaleOption[] };
    warehouses: WarehouseOption[];
    categories: CategoryOption[];
    uoms: UomOption[];
    sellers: SellerOption[];
    customers: CustomerOption[];
    priceLists: PriceListOption[];
    variants: VariantOption[];
    surcharges: SurchargeOption[];
    stockPolicy: { allow_negative_stock: boolean };
    invoicePolicy: { use_invoice_on_sale: boolean };
    editingOrder?: {
        id: number;
        code: string;
        status: string;
        type: 'RETAIL' | 'WHOLESALE' | 'ONLINE' | 'RETURN';
        customer_id: number | null;
        seller_id: number | null;
        warehouse_id: number | null;
        order_date?: string | null;
        discount?: number;
        paid_amount?: number;
        note?: string;
        invoice_id?: number | null;
        invoice_status?: string | null;
        surcharge_id?: number | null;
        items: Array<{
            order_item_id?: number;
            product_variant_id: number;
            unit_name?: string;
            conversion_rate?: number;
            quantity: number;
            price: number;
            note?: string;
        }>;
    } | null;
}>();

const tabs = ref<PosDraftTab[]>([]);
const activeTabId = ref<string>('');
const localVariants = ref<VariantOption[]>([]);
const localCustomers = ref<CustomerOption[]>([]);
const customerSearchQuery = ref('');
const customerModalOpen = ref(false);
const addProductModalOpen = ref(false);
const addProductModalProcessing = ref(false);
const addProductSeedCode = ref('');
const successModalOpen = ref(false);
const checkoutWarningMessage = ref('');
const posMode = ref<'RETAIL' | 'PREORDER' | 'RETURN'>('RETAIL');
const preorderStatus = ref<'PREORDER' | 'DRAFT'>('PREORDER');
const returnSourceOrderId = ref<number | null>(null);
const successModalData = ref({
    totalAmount: 0,
    amountReceived: 0,
    changeAmount: 0,
    itemCount: 0,
    customerName: 'Khách lẻ',
    createdOrderRef: null as string | number | null,
    createdMode: 'RETAIL' as 'RETAIL' | 'PREORDER' | 'RETURN',
    createdPreorderStatus: 'PREORDER' as 'PREORDER' | 'DRAFT',
});
let orderDateRealtimeInterval: number | null = null;
const lastPosActivityAt = ref<number>(Date.now());
const shouldSkipDraftRestore = ref(false);
const selectOrderModalOpen = ref(false);
const launchEntry = ref('');
const manualOrderDateByTabId = ref<Record<string, boolean>>({});
const editingOrder = computed(() => props.editingOrder ?? null);
const isEditingOrder = computed(() => editingOrder.value !== null);
const editingOrderRef = computed(() => String(editingOrder.value?.code ?? '').trim());

const getCurrentTabLabel = (index: number) => {
    if (posMode.value === 'RETURN') {
        return `Trả hàng ${index}`;
    }

    if (posMode.value === 'PREORDER') {
        return preorderStatus.value === 'DRAFT' ? `Nháp ${index}` : `Đặt trước ${index}`;
    }

    return `Hóa đơn ${index}`;
};

const form = useForm({
    customer_id: null as number | null,
    seller_id: null as number | null,
    warehouse_id: null as number | null,
    status: 'COMPLETED' as 'CONFIRMED' | 'DRAFT' | 'COMPLETED' | 'PREORDER',
    create_receivable: false,
    paid_amount: 0,
    replace_invoice: false,
    revision_reason: '',
    order_date: '' as string,
    type: 'RETAIL' as 'RETAIL' | 'WHOLESALE',
    discount: 0,
    note: '',
    items: [] as Array<{
        product_variant_id: number;
        unit_name: string;
        conversion_rate: number;
        quantity: number;
        price: number;
        discount: number;
        tax_rate: number;
        note: string;
    }>,
    surcharges: [] as Array<{
        name: string;
        type: 'PERCENT' | 'FIXED_AMOUNT';
        value: number;
        amount: number;
        is_refundable: boolean;
    }>,
});

const createDefaultTab = (index: number): PosDraftTab => ({
    id: createTabId(),
    label: getCurrentTabLabel(index),
    search_query: '',
    customer_id: null,
    seller_id: props.sellers[0]?.id ?? null,
    warehouse_id: props.warehouses[0]?.id ?? null,
    order_type: 'RETAIL',
    price_list_id: null,
    order_date: toDatetimeLocal(),
    surcharge_id: null,
    discount_amount: 0,
    amount_received: null,
    note: '',
    items: [],
});

const activeTab = computed(() => tabs.value.find((tab) => tab.id === activeTabId.value) ?? null);

const selectedWarehouseId = computed<number | null>({
    get: () => activeTab.value?.warehouse_id ?? null,
    set: (value) => {
        if (activeTab.value) activeTab.value.warehouse_id = value;
    },
});

const selectedCustomerId = computed<number | null>({
    get: () => activeTab.value?.customer_id ?? null,
    set: (value) => {
        if (activeTab.value) activeTab.value.customer_id = value;
    },
});

const selectedSellerId = computed<number | null>({
    get: () => activeTab.value?.seller_id ?? null,
    set: (value) => {
        if (activeTab.value) activeTab.value.seller_id = value;
    },
});

const selectedType = computed<'RETAIL' | 'WHOLESALE'>({
    get: () => activeTab.value?.order_type ?? 'RETAIL',
    set: (value) => {
        if (activeTab.value) activeTab.value.order_type = value;
    },
});

const selectedPriceListId = computed<number | null>({
    get: () => activeTab.value?.price_list_id ?? null,
    set: (value) => {
        if (activeTab.value) activeTab.value.price_list_id = value;
    },
});

const selectedOrderDate = computed<string>({
    get: () => activeTab.value?.order_date ?? toDatetimeLocal(),
    set: (value) => {
        if (!activeTab.value) return;
        activeTab.value.order_date = value;
        manualOrderDateByTabId.value[activeTab.value.id] = true;
    },
});

const selectedSurchargeId = computed<number | null>({
    get: () => activeTab.value?.surcharge_id ?? null,
    set: (value) => {
        if (activeTab.value) activeTab.value.surcharge_id = value;
    },
});

const discountAmount = computed<number>({
    get: () => activeTab.value?.discount_amount ?? 0,
    set: (value) => {
        if (activeTab.value) activeTab.value.discount_amount = Number(value || 0);
    },
});

const amountReceived = computed<number>({
    get: () => activeTab.value?.amount_received ?? grandTotal.value,
    set: (value) => {
        if (activeTab.value) activeTab.value.amount_received = Number(value || 0);
    },
});

const orderNote = computed<string>({
    get: () => activeTab.value?.note ?? '',
    set: (value) => {
        if (activeTab.value) activeTab.value.note = value;
    },
});

const shouldCreateReceivable = computed(() => {
    return selectedCustomerId.value !== null && Number(amountReceived.value || 0) === 0 && grandTotal.value > 0;
});

const useInvoiceOnSale = computed(() => Boolean(props.invoicePolicy?.use_invoice_on_sale));

const orderConfigWarnings = computed(() => {
    const warnings: string[] = [];

    if (selectedWarehouseId.value === null) {
        warnings.push('Vui lòng chọn Kho trước khi thanh toán.');
    }

    if (selectedSellerId.value === null) {
        warnings.push('Vui lòng chọn Người bán trước khi thanh toán.');
    }

    if (!['RETAIL', 'WHOLESALE'].includes(selectedType.value)) {
        warnings.push('Vui lòng chọn Loại đơn (Buôn/Lẻ) hợp lệ.');
    }

    return warnings;
});

const searchQuery = computed<string>({
    get: () => activeTab.value?.search_query ?? '',
    set: (value) => {
        if (activeTab.value) activeTab.value.search_query = value;
    },
});

const cart = computed<CartItem[]>(() => activeTab.value?.items ?? []);

const selectedSurcharge = computed(() => props.surcharges.find((item) => item.id === selectedSurchargeId.value) ?? null);
const selectedCustomer = computed(() => selectableCustomers.value.find((item) => item.id === selectedCustomerId.value) ?? null);
const selectedPriceList = computed(() => props.priceLists.find((item) => item.id === selectedPriceListId.value) ?? null);

const {
    buildVariantDisplayName,
    getVariantById,
    getVariantUnitOptions,
    normalizeCartItem,
    filteredVariants,
    hasSearchQuery,
    resolvePriceForVariant,
    getUnitPriceDisplay,
} = usePosPrice({
    localVariants,
    selectedPriceList,
    searchQuery,
});

const subtotal = computed(() => cart.value.reduce((sum, item) => sum + item.price * item.quantity, 0));
const allowNegativeStock = computed(() => Boolean(props.stockPolicy?.allow_negative_stock));

const selectedPriceListLabel = computed(() => {
    if (!selectedPriceList.value) return 'Giá bán mặc định';

    if (selectedPriceList.value.code) {
        return `${selectedPriceList.value.name} (${selectedPriceList.value.code})`;
    }

    return selectedPriceList.value.name;
});

const priceListValue = computed({
    get: () => (selectedPriceListId.value === null ? '' : String(selectedPriceListId.value)),
    set: (value: string) => {
        selectedPriceListId.value = value === '' ? null : Number(value);
    },
});

const surchargeAmount = computed(() => {
    if (!selectedSurcharge.value) return 0;
    if (selectedSurcharge.value.type === 'PERCENT') {
        const amount = (subtotal.value * Number(selectedSurcharge.value.value || 0)) / 100;
        return Math.max(0, Number(amount.toFixed(2)));
    }

    return Math.max(0, Number(selectedSurcharge.value.value || 0));
});

const grandTotal = computed(() => Math.max(0, subtotal.value - Number(discountAmount.value || 0) + surchargeAmount.value));

const surchargeLabel = computed(() => {
    if (!selectedSurcharge.value) return 'Không áp dụng phụ thu';

    if (selectedSurcharge.value.type === 'PERCENT') {
        return `${selectedSurcharge.value.name} (${selectedSurcharge.value.value}%)`;
    }

    return `${selectedSurcharge.value.name} (${Number(selectedSurcharge.value.value).toLocaleString('vi-VN')})`;
});

const customerLabel = computed(() => {
    if (!selectedCustomer.value) return 'Tìm khách hàng (F4)';
    return selectedCustomer.value.phone
        ? `${selectedCustomer.value.name} (${selectedCustomer.value.phone})`
        : selectedCustomer.value.name;
});

const normalizeCustomerName = (value: string | null | undefined): string => {
    return String(value ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .trim();
};

const isWalkInCustomer = (customer: CustomerOption): boolean => {
    const normalized = normalizeCustomerName(customer.name);
    return normalized === 'khach le' || normalized === 'guest';
};

const selectableCustomers = computed(() => {
    return localCustomers.value.filter((customer) => !isWalkInCustomer(customer));
});

const customerValue = computed({
    get: () => (selectedCustomerId.value === null ? '' : String(selectedCustomerId.value)),
    set: (value: string) => {
        selectedCustomerId.value = value === '' ? null : Number(value);
    },
});

const filteredCustomerOptions = computed(() => {
    const query = customerSearchQuery.value.trim().toLowerCase();
    const customers = selectableCustomers.value;

    if (query === '') {
        return customers;
    }

    return customers.filter((customer) => {
        const text = `${customer.code ?? ''} ${customer.name} ${customer.phone ?? ''}`.toLowerCase();
        return text.includes(query);
    });
});

const {
    productInfoModalOpen,
    priceHistoryModalOpen,
    itemNoteModalOpen,
    targetCartIndex,
    itemNoteDraft,
    targetCartItem,
    targetVariant,
    priceHistoryTitle,
    stockWarnings,
    isItemOutOfStock,
    addToCart,
    increaseQty,
    decreaseQty,
    removeItem,
    updateItemUnit,
    updateItemPrice,
    openProductInfo,
    openPriceHistory,
    openItemNote,
    applyItemNote,
    applyHistoryPrice,
    clearAllItems,
} = usePosCart({
    mode: posMode,
    activeTab,
    cart,
    searchQuery,
    localVariants,
    selectedWarehouseId,
    allowNegativeStock,
    grandTotal,
    resolvePriceForVariant,
    getVariantById,
    getVariantUnitOptions,
    buildVariantDisplayName,
});

const paidByCustomer = computed(() => Math.max(0, Number(amountReceived.value || 0)));
const amountRemaining = computed(() => Math.max(0, grandTotal.value - paidByCustomer.value));
const amountExcess = computed(() => Math.max(0, paidByCustomer.value - grandTotal.value));
const changeAmount = computed(() => amountExcess.value);

const moneySuggestions = computed(() => {
    const total = Math.ceil(grandTotal.value);
    const ceilBy = (amount: number, base: number) => Math.ceil(amount / base) * base;

    const values = [total, ceilBy(total, 10000), ceilBy(total, 50000)]
        .filter((value) => Number.isFinite(value) && value > 0)
        .filter((value, index, arr) => arr.indexOf(value) === index)
        .slice(0, 3);

    while (values.length < 3) {
        const last = values[values.length - 1] ?? total;
        values.push(last + 50000);
    }

    return values;
});

const handleSearchEnter = () => {
    const firstVariant = filteredVariants.value[0];
    if (!firstVariant) return;
    addToCart(firstVariant);
};

const startNewOrder = () => {
    const tab = activeTab.value;
    if (!tab) return;

    tab.search_query = '';
    tab.customer_id = null;
    tab.seller_id = props.sellers[0]?.id ?? null;
    tab.warehouse_id = props.warehouses[0]?.id ?? null;
    tab.order_type = 'RETAIL';
    tab.price_list_id = null;
    tab.surcharge_id = null;
    tab.discount_amount = 0;
    tab.amount_received = null;
    tab.note = '';
    tab.items = [];
    tab.order_date = toDatetimeLocal();
    customerSearchQuery.value = '';
    returnSourceOrderId.value = null;
    manualOrderDateByTabId.value[tab.id] = false;
};

const { persistTabs, ensureActiveTab, restoreTabs, addOrderTab, closeOrderTab } = usePosTabs({
    tabs,
    activeTabId,
    storageKey: POS_DRAFT_STORAGE_KEY,
    createDefaultTab,
    normalizeCartItem,
    startNewOrder,
    toDatetimeLocal,
});

const selectedCustomerName = computed(() => selectedCustomer.value?.name ?? 'Khách lẻ');

const returnSourceSales = computed(() => {
    return props.sales.data.filter((item) => typeof item.id === 'number' && Boolean(item.code));
});

const { submitPos, returnSourceOptions } = usePos({
    mode: posMode,
    preorderStatus,
    returnSourceOrderId,
    activeTab,
    selectedWarehouseId,
    selectedSellerId,
    selectedType,
    selectedSurcharge,
    selectedCustomerName,
    shouldCreateReceivable,
    amountReceived,
    surchargeAmount,
    grandTotal,
    checkoutWarningMessage,
    successModalData,
    successModalOpen,
    form,
    sales: returnSourceSales,
    onSuccessReset: startNewOrder,
    editingOrderRef,
    editingOrderStatus: computed(() => String(editingOrder.value?.status ?? '').toUpperCase()),
    editingOrderHasInvoice: computed(() => Number(editingOrder.value?.invoice_id || 0) > 0),
    useInvoiceOnSale: computed(() => Boolean(props.invoicePolicy?.use_invoice_on_sale)),
});

const applyEditingOrderToCurrentTab = () => {
    const source = editingOrder.value;
    const tab = activeTab.value;
    if (!source || !tab) return;

    tab.label = `Sửa ${source.code}`;
    tab.customer_id = source.customer_id;
    tab.seller_id = source.seller_id;
    tab.warehouse_id = source.warehouse_id;
    tab.order_type = String(source.type ?? 'RETAIL').toUpperCase() === 'WHOLESALE' ? 'WHOLESALE' : 'RETAIL';
    tab.order_date = source.order_date ?? toDatetimeLocal();
    tab.surcharge_id = source.surcharge_id ?? null;
    tab.discount_amount = Number(source.discount ?? 0);
    tab.amount_received = Number(source.paid_amount ?? 0);
    tab.note = source.note ?? '';
    tab.items = source.items
        .map((item) => {
            const variantId = Number(item.product_variant_id ?? 0);
            if (!Number.isFinite(variantId) || variantId <= 0) return null;

            const variant = getVariantById(variantId);
            const unitOptions = getVariantUnitOptions(variant);
            const fallbackUnit = unitOptions[0] ?? { name: 'Cái', conversion_rate: 1 };
            const sourceUnit = String(item.unit_name ?? '').trim();
            const selectedUnit = sourceUnit !== ''
                ? (unitOptions.find((option) => option.name === sourceUnit) ?? fallbackUnit)
                : fallbackUnit;

            return {
                order_item_id: item.order_item_id,
                product_variant_id: variantId,
                name: buildVariantDisplayName(variant?.product_name, variant?.name) || `Biến thể #${variantId}`,
                sku: variant?.sku ?? null,
                unit_name: selectedUnit.name,
                conversion_rate: Math.max(1, Math.round(Number(item.conversion_rate ?? selectedUnit.conversion_rate ?? 1))),
                unit_options: unitOptions.length > 0 ? unitOptions : [fallbackUnit],
                price: Number(item.price ?? 0),
                quantity: Math.max(1, Math.round(Number(item.quantity ?? 1))),
                note: item.note ?? '',
            };
        })
        .filter((row): row is NonNullable<typeof row> => row !== null);
};

const onCreateNewFromSuccess = () => {
    successModalOpen.value = false;
    startNewOrder();
};

const onViewOrderFromSuccess = (orderRef: string | number) => {
    successModalOpen.value = false;
    router.get(tenantRoutes.orders.show(orderRef));
};

const openSelectOrderModal = () => {
    selectOrderModalOpen.value = true;
};

const focusSearchInput = () => {
    if (typeof window === 'undefined') return;

    window.requestAnimationFrame(() => {
        const input = document.querySelector('input[placeholder="Tìm hàng hóa (F3)"]') as HTMLInputElement | null;
        input?.focus();
        input?.select();
    });
};

const loadReturnItemsFromSourceOrder = (orderId: number) => {
    const tab = activeTab.value;
    if (!tab) return;

    const sourceOrder = returnSourceOptions.value.find((item) => Number(item.id) === Number(orderId));
    if (!sourceOrder) return;

    tab.customer_id = sourceOrder.customer_id ?? null;

    const mappedItems = (sourceOrder.items ?? [])
        .map((item) => {
            const variantId = Number(item.product_variant_id ?? 0);
            if (!Number.isFinite(variantId) || variantId <= 0) return null;

            const variant = getVariantById(variantId);
            const unitOptions = getVariantUnitOptions(variant);
            const fallbackUnit = unitOptions[0] ?? { name: 'Cái', conversion_rate: 1 };
            const sourceUnit = String(item.unit_name ?? '').trim();
            const selectedUnit = sourceUnit !== ''
                ? (unitOptions.find((option) => option.name === sourceUnit) ?? fallbackUnit)
                : fallbackUnit;

            const sourceQty = Number(item.quantity ?? 1);
            const soldQuantity = Number.isFinite(sourceQty) && sourceQty > 0 ? Math.round(sourceQty) : 0;
            const sourceReturnedQty = Number(item.returned_quantity ?? 0);
            const returnedQuantity = Number.isFinite(sourceReturnedQty) && sourceReturnedQty > 0 ? Math.round(sourceReturnedQty) : 0;
            const remainingQuantity = Math.max(0, soldQuantity - returnedQuantity);
            const quantity = 0;

            const sourcePrice = Number(item.price ?? 0);
            const price = Number.isFinite(sourcePrice) && sourcePrice >= 0
                ? sourcePrice
                : resolvePriceForVariant(variantId, selectedUnit.name, quantity);

            return {
                order_item_id: Number(item.id ?? 0) > 0 ? Number(item.id) : undefined,
                product_variant_id: variantId,
                name: buildVariantDisplayName(variant?.product_name, variant?.name) || `Biến thể #${variantId}`,
                sku: variant?.sku ?? null,
                unit_name: selectedUnit.name,
                conversion_rate: Number(selectedUnit.conversion_rate || 1),
                unit_options: unitOptions.length > 0 ? unitOptions : [fallbackUnit],
                price,
                quantity,
                sold_quantity: remainingQuantity,
                returned_quantity: returnedQuantity,
                note: '',
            };
        })
        .filter((row): row is NonNullable<typeof row> => row !== null);

    tab.items = mappedItems;
};

const onSelectReturnSourceOrder = (orderId: number) => {
    returnSourceOrderId.value = orderId;
    loadReturnItemsFromSourceOrder(orderId);
    selectOrderModalOpen.value = false;
};

const onSelectOrderModalOpenChange = (value: boolean) => {
    selectOrderModalOpen.value = value;

    if (value) {
        return;
    }

    if (posMode.value === 'RETURN' && returnSourceOrderId.value === null) {
        posMode.value = 'RETAIL';
    }
};

const {
    openAddProductModalFromSearch,
    handleAddProductSubmit,
    openCustomerModal,
    handleCustomerSaved,
} = usePosCatalogActions({
    addProductModalOpen,
    addProductModalProcessing,
    addProductSeedCode,
    customerModalOpen,
    customerSearchQuery,
    localVariants,
    localCustomers,
    searchQuery,
    selectedCustomerId,
    warehouses: props.warehouses,
    addToCart,
    onAddProductSuccess: focusSearchInput,
});

const { refreshPosData, stopSyncPolling, startSyncPolling, markPosActivity } = useAdaptiveSync({
    activeTab,
    searchQuery,
    lastPosActivityAt,
    router,
});

const { bindVisibilitySync, unbindVisibilitySync } = useVisibilitySync({
    onVisible: () => {
        refreshPosData('visibility');
        startSyncPolling();
    },
    onHidden: () => {
        stopSyncPolling();
    },
    onFocus: () => {
        refreshPosData('focus');
    },
});

const applyLaunchModeFromQuery = () => {
    if (typeof window === 'undefined') return;

    const query = new URLSearchParams(window.location.search);
    launchEntry.value = query.get('entry') ?? '';
    const queryMode = (query.get('mode') ?? '').toUpperCase();
    const queryPreorderStatus = (query.get('preorderStatus') ?? '').toUpperCase();

    if (queryMode === 'PREORDER' || queryMode === 'RETURN' || queryMode === 'RETAIL') {
        posMode.value = queryMode;
    }

    if (queryPreorderStatus === 'PREORDER' || queryPreorderStatus === 'DRAFT') {
        preorderStatus.value = queryPreorderStatus;
    }

    if (launchEntry.value === 'tenant-header' || launchEntry.value === 'returns-index') {
        shouldSkipDraftRestore.value = true;
        const tab = createDefaultTab(1);
        tab.items = [];
        tabs.value = [tab];
        activeTabId.value = tab.id;
    }

    if (editingOrder.value) {
        shouldSkipDraftRestore.value = true;
        const tab = createDefaultTab(1);
        tabs.value = [tab];
        activeTabId.value = tab.id;
    }
};

onMounted(() => {
    applyLaunchModeFromQuery();

    localVariants.value = [...props.variants];
    localCustomers.value = [...props.customers];
    if (!shouldSkipDraftRestore.value) {
        restoreTabs();
    } else {
        ensureActiveTab();
    }

    if (activeTab.value) {
        activeTab.value.label = getCurrentTabLabel(1);
    }

    if (editingOrder.value) {
        posMode.value = 'RETAIL';
        applyEditingOrderToCurrentTab();
    }

    if (posMode.value === 'RETURN' && launchEntry.value === 'returns-index') {
        selectOrderModalOpen.value = true;
    }

    bindVisibilitySync();
    startSyncPolling();

    orderDateRealtimeInterval = window.setInterval(() => {
        const tab = activeTab.value;
        if (!tab) return;
        if (manualOrderDateByTabId.value[tab.id]) return;
        tab.order_date = toDatetimeLocal(new Date());
    }, 1000);
});

onUnmounted(() => {
    if (orderDateRealtimeInterval !== null) {
        window.clearInterval(orderDateRealtimeInterval);
    }

    stopSyncPolling();
    unbindVisibilitySync();
});

watch(
    () => props.variants,
    (variants) => {
        localVariants.value = [...variants];

        const tab = activeTab.value;
        if (!tab) return;

        tab.items = tab.items.map((item) => {
            const normalized = normalizeCartItem(item);
            return {
                ...normalized,
                price: resolvePriceForVariant(normalized.product_variant_id, normalized.unit_name, normalized.quantity),
            };
        });
    },
    { deep: true },
);

watch(
    () => props.customers,
    (customers) => {
        localCustomers.value = [...customers];
    },
    { deep: true },
);

watch(selectedCustomerId, () => {
    if (selectedCustomerId.value !== null) {
        const currentCustomer = localCustomers.value.find((item) => item.id === selectedCustomerId.value) ?? null;
        if (currentCustomer && isWalkInCustomer(currentCustomer)) {
            selectedCustomerId.value = null;
            return;
        }
    }

    selectedPriceListId.value = selectedCustomer.value?.price_list_id ?? null;
});

watch(selectedPriceListId, () => {
    const tab = activeTab.value;
    if (!tab) return;

    tab.items = tab.items.map((item) => ({
        ...item,
        price: resolvePriceForVariant(item.product_variant_id, item.unit_name, item.quantity),
    }));
});

watch(
    tabs,
    () => {
        ensureActiveTab();
        persistTabs();
    },
    { deep: true },
);

watch(activeTabId, () => {
    ensureActiveTab();
    persistTabs();
});

watch(
    () => [activeTab.value?.items.length ?? 0, searchQuery.value],
    () => {
        if ((activeTab.value?.items.length ?? 0) > 0) {
            checkoutWarningMessage.value = '';
        }
        markPosActivity();
        startSyncPolling();
    },
);

watch(posMode, (mode) => {
    if (mode !== 'RETURN') {
        returnSourceOrderId.value = null;
    }

    if (activeTab.value) {
        activeTab.value.label = getCurrentTabLabel(1);
    }
});

watch(preorderStatus, () => {
    if (posMode.value !== 'PREORDER') {
        return;
    }

    if (activeTab.value) {
        activeTab.value.label = getCurrentTabLabel(1);
    }
});
</script>

<template>
    <Head title="Bán Hàng" />
    <TenantLayout>
        <template #header>
            <PosTenantHeader
                v-model:warehouse-id="selectedWarehouseId"
                v-model:seller-id="selectedSellerId"
                v-model:type="selectedType"
                :warehouses="props.warehouses"
                :sellers="props.sellers"
            />
        </template>

        <div class="h-full w-full">
            <section class="h-full w-full overflow-hidden bg-card">
                <div class="grid h-full gap-0 xl:grid-cols-[minmax(0,1fr)_450px]">
                    <div class="flex min-h-0 flex-col">
                        <div class="border-b border-border/70 bg-card px-3 py-2">
                            <div class="flex items-center gap-2">
                                <div class="relative w-full w-full max-w-sm">
                                    <Search class="pointer-events-none absolute left-3 top-2.5 size-4 text-muted-foreground" />
                                    <Input
                                        v-model="searchQuery"
                                        class="h-10 pl-9"
                                        placeholder="Tìm hàng hóa (F3)"
                                        @keydown.enter.prevent="handleSearchEnter"
                                    />

                                    <div
                                        v-if="hasSearchQuery"
                                        class="absolute z-20 mt-1 w-full rounded-md border border-border bg-popover shadow-md"
                                    >
                                        <div class="max-h-64 overflow-y-auto p-1">
                                            <template v-if="filteredVariants.length > 0">
                                                <button
                                                    v-for="variant in filteredVariants"
                                                    :key="variant.id"
                                                    type="button"
                                                    class="flex w-full items-center justify-between rounded px-2 py-2 text-left text-sm hover:bg-muted"
                                                    @click="addToCart(variant)"
                                                >
                                                    <span class="truncate">{{ buildVariantDisplayName(variant.product_name, variant.name) }}</span>
                                                    <span class="ml-2 text-xs text-muted-foreground">{{ Number(variant.price || 0).toLocaleString('vi-VN') }}</span>
                                                </button>
                                            </template>
                                            <p v-else class="px-2 py-2 text-xs text-muted-foreground">Không tìm thấy hàng hóa phù hợp.</p>
                                        </div>

                                        <div class="border-t border-border/70 p-2">
                                            <Button type="button" variant="secondary" class="h-9 w-full justify-start" @click="openAddProductModalFromSearch">
                                                <Plus class="size-4" />
                                                Thêm hàng hóa mới
                                            </Button>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-1 items-center gap-2 overflow-x-auto">
                                    <div
                                        v-for="tab in tabs"
                                        :key="tab.id"
                                        class="inline-flex h-10 shrink-0 items-center rounded-md border px-2 text-sm"
                                        :class="activeTabId === tab.id ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-background'"
                                    >
                                        <button type="button" class="px-1" @click="activeTabId = tab.id">{{ tab.label }}</button>
                                        <button type="button" class="rounded p-0.5 hover:bg-muted" @click="closeOrderTab(tab.id)">
                                            <X class="size-3.5" />
                                        </button>
                                    </div>
                                </div>

                                <Button type="button" variant="secondary" size="icon" class="h-10 w-10" @click="addOrderTab" aria-label="Thêm hóa đơn mới">
                                    <Plus class="size-4" />
                                </Button>
                            </div>
                        </div>

                        <div class="flex-1 overflow-auto p-3">
                            <div v-if="cart.length === 0" class="flex h-full items-center justify-center rounded-xl text-sm">
                                Chưa có sản phẩm trong hóa đơn. Gõ tên hàng để thêm nhanh.
                            </div>

                            <div v-else class="space-y-3">
                                <Alert v-if="stockWarnings.length > 0" variant="destructive" class="border-destructive/60">
                                    <AlertTriangle class="size-4" />
                                    <AlertTitle>Cảnh báo thiếu tồn kho</AlertTitle>
                                    <AlertDescription>
                                        <p>
                                            Có {{ stockWarnings.length }} sản phẩm đang vượt tồn khả dụng theo kho đã chọn.
                                        </p>
                                        <p
                                            v-for="warning in stockWarnings"
                                            :key="warning.product_variant_id"
                                            class="text-sm"
                                        >
                                            {{ warning.display_name }}: cần {{ warning.required_base_stock.toLocaleString('vi-VN') }}, hiện có {{ warning.available_base_stock.toLocaleString('vi-VN') }}.
                                        </p>
                                    </AlertDescription>
                                </Alert>

                                <div class="overflow-hidden rounded-xl border border-border/70 bg-background">
                                <table class="w-full text-sm">
                                    <thead class="bg-muted/60 text-left">
                                        <tr>
                                            <th class="p-3 font-medium">Hàng hóa</th>
                                            <th class="p-3 text-center align-middle">
                                                <div class="flex items-center justify-center">
                                                    <Info class="size-4" />
                                                </div>
                                            </th>
                                            <th class="p-3 text-center font-medium">SL</th>
                                            <th class="p-3 text-center font-medium">ĐVT</th>
                                            <th class="p-3 text-right font-medium">Đơn giá</th>
                                            <th class="p-3 text-right font-medium">Thành tiền</th>
                                            <th class="p-3 text-center font-medium">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(item, index) in cart" :key="`${item.product_variant_id}-${index}`" class="border-t">
                                            <td class="p-3">
                                                <div class="flex items-start justify-between gap-2">
                                                    <p class="leading-tight" :class="isItemOutOfStock(item) ? 'font-extrabold text-destructive' : 'font-medium'">{{ item.name }}</p>
                                                </div>
                                                <p class="text-xs text-muted-foreground">{{ item.sku || '-' }}</p>
                                                <p v-if="item.note" class="mt-1 line-clamp-2 text-xs text-muted-foreground">{{ item.note }}</p>
                                            </td>
                                            <td class="p-3 text-center align-middle">
                                                <div class="flex items-center justify-center gap-1">
                                                    <Button type="button" variant="ghost" size="icon-sm" @click="openProductInfo(index)">
                                                        <Info class="size-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                            <td class="p-3">
                                                <div class="flex items-center justify-center gap-1">
                                                    <Button type="button" variant="outline" size="icon-sm" @click="decreaseQty(index)"><Minus class="size-3.5" /></Button>
                                                    <div class="flex min-w-14 flex-col items-center">
                                                        <span class="w-8 text-center">{{ item.quantity }}</span>
                                                        <span v-if="posMode === 'RETURN' && Number(item.sold_quantity || 0) > 0" class="text-xs text-muted-foreground">/ {{ Number(item.sold_quantity || 0) }}</span>
                                                    </div>
                                                    <Button type="button" variant="outline" size="icon-sm" @click="increaseQty(index)"><Plus class="size-3.5" /></Button>
                                                </div>
                                            </td>
                                            <td class="p-3">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger as-child>
                                                        <Button type="button" variant="outline" class="h-8 w-full justify-between px-2 text-xs font-normal">
                                                            <span class="truncate">{{ item.unit_name }}</span>
                                                            <ChevronDown class="size-3.5 text-muted-foreground" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="start" class="min-w-56">
                                                        <DropdownMenuLabel>Đơn vị bán</DropdownMenuLabel>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuRadioGroup :model-value="item.unit_name" @update:model-value="(value) => updateItemUnit(index, String(value))">
                                                            <DropdownMenuRadioItem v-for="unit in item.unit_options" :key="`${item.product_variant_id}-${unit.name}`" :value="unit.name">
                                                                {{ unit.name }} (x{{ Number(unit.conversion_rate || 1).toLocaleString('vi-VN') }}) - {{ getUnitPriceDisplay(item.product_variant_id, unit.name, item.quantity) }}
                                                            </DropdownMenuRadioItem>
                                                        </DropdownMenuRadioGroup>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                            <td class="p-3 text-right">
                                                <Input
                                                    :model-value="item.price"
                                                    type="number"
                                                    min="0"
                                                    step="1"
                                                    class="ml-auto h-8 w-28 text-right tabular-nums outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                                    @update:model-value="(value) => updateItemPrice(index, value as number | string)"
                                                />
                                            </td>
                                            <td class="p-3 text-right font-medium">{{ (item.price * item.quantity).toLocaleString('vi-VN') }}</td>
                                            <td class="p-3 text-center">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger as-child>
                                                        <Button type="button" variant="ghost" size="icon-sm">
                                                            <MoreVertical class="size-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end" class="min-w-48">
                                                        <DropdownMenuItem @click="openItemNote(index)">
                                                            Ghi chú sản phẩm
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem @click="openPriceHistory(index)">
                                                            Xem giá bán gần đây
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                                <Button type="button" variant="ghost" size="icon-sm" @click="removeItem(index)">
                                                    <Trash2 class="size-4" />
                                                </Button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            </div>
                        </div>

                        <div class="border-t border-border/70 bg-background p-3">
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <Label class="mb-2">Ghi chú đơn hàng</Label>
                                    <Input v-model="orderNote" placeholder="Nhập ghi chú cho đơn hàng" />
                                </div>

                                <Button type="button" variant="outline" size="icon" class="h-10 w-10" @click="clearAllItems" aria-label="Dọn đơn hàng">
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div class="flex min-h-0 flex-col border-border/70 bg-card">
                        <div class="space-y-3 border-b border-border/70 p-4">
                            <Alert v-if="orderConfigWarnings.length > 0" variant="destructive" class="mb-2">
                                <AlertTriangle class="size-4" />
                                <AlertTitle>Thiếu thông tin đơn hàng</AlertTitle>
                                <AlertDescription>
                                    <p
                                        v-for="warning in orderConfigWarnings"
                                        :key="warning"
                                    >
                                        {{ warning }}
                                    </p>
                                </AlertDescription>
                            </Alert>

                            <div class="grid grid-cols-2 gap-2">
                                <div class="min-w-0">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="outline" class="h-10 w-full justify-between font-normal">
                                                <span class="truncate text-left">{{ customerLabel }}</span>
                                                <ChevronDown class="size-4 text-muted-foreground" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="start" class="max-h-80 w-[var(--reka-dropdown-menu-trigger-width)] min-w-72 overflow-y-auto">
                                            <DropdownMenuLabel>Khách hàng</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <div class="px-2 pb-2" @click.stop>
                                                <Input v-model="customerSearchQuery" class="h-9" placeholder="Nhập tên/sđt/mã khách hàng" />
                                            </div>
                                            <div class="px-2 pb-2" @click.stop>
                                                <Button type="button" variant="secondary" class="h-9 w-full justify-start" @click="openCustomerModal">
                                                    <Plus class="size-4" />
                                                    Tạo mới khách hàng
                                                </Button>
                                            </div>
                                            <DropdownMenuRadioGroup v-model="customerValue">
                                                <DropdownMenuRadioItem value="">Khách lẻ</DropdownMenuRadioItem>
                                                <DropdownMenuRadioItem v-for="customer in filteredCustomerOptions" :key="customer.id" :value="String(customer.id)">
                                                    {{ customer.name }}{{ customer.phone ? ` (${customer.phone})` : '' }}
                                                </DropdownMenuRadioItem>
                                            </DropdownMenuRadioGroup>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>

                                <div class="min-w-0">
                                    <DateTimePicker v-model="selectedOrderDate" expand />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div v-if="posMode === 'RETURN'">
                                    <Label class="mb-1 text-xs">Đơn hàng nguồn</Label>
                                    <Button variant="outline" class="h-9 w-full justify-between font-normal" @click="openSelectOrderModal">
                                        <span class="truncate">{{ returnSourceOrderId ? (returnSourceOptions.find((item) => Number(item.id) === Number(returnSourceOrderId))?.code || `#${returnSourceOrderId}`) : 'Chọn đơn hàng nguồn' }}</span>
                                        <ChevronDown class="size-4 text-muted-foreground" />
                                    </Button>
                                </div>

                                <div>
                                    <Label class="mb-1 text-xs">Bảng giá</Label>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="outline" class="h-9 w-full justify-between font-normal">
                                                <span class="truncate">{{ selectedPriceListLabel }}</span>
                                                <ChevronDown class="size-4 text-muted-foreground" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-60">
                                            <DropdownMenuLabel>Chọn bảng giá</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuRadioGroup v-model="priceListValue">
                                                <DropdownMenuRadioItem value="">Giá bán mặc định</DropdownMenuRadioItem>
                                                <DropdownMenuRadioItem v-for="priceList in props.priceLists" :key="priceList.id" :value="String(priceList.id)">
                                                    {{ priceList.name }}{{ priceList.code ? ` (${priceList.code})` : '' }}
                                                </DropdownMenuRadioItem>
                                            </DropdownMenuRadioGroup>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>

                                <div>
                                    <Label class="mb-1 text-xs">Phụ thu</Label>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="outline" class="h-9 w-full justify-between font-normal">
                                                <span class="truncate">{{ surchargeLabel }}</span>
                                                <ChevronDown class="size-4 text-muted-foreground" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="w-[var(--reka-dropdown-menu-trigger-width)] min-w-60">
                                            <DropdownMenuLabel>Chọn phụ thu</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuRadioGroup :model-value="selectedSurchargeId === null ? '' : String(selectedSurchargeId)" @update:model-value="selectedSurchargeId = $event === '' ? null : Number($event)">
                                                <DropdownMenuRadioItem value="">Không áp dụng phụ thu</DropdownMenuRadioItem>
                                                <DropdownMenuRadioItem v-for="surcharge in props.surcharges" :key="surcharge.id" :value="String(surcharge.id)">
                                                    {{ surcharge.name }}
                                                    ({{ surcharge.type === 'PERCENT' ? `${surcharge.value}%` : Number(surcharge.value).toLocaleString('vi-VN') }})
                                                </DropdownMenuRadioItem>
                                            </DropdownMenuRadioGroup>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </div>
                        </div>

                        <div class="flex-1 space-y-3 p-4 text-base">
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground">Tổng tiền hàng</span>
                                <span class="font-medium">{{ subtotal.toLocaleString('vi-VN') }}</span>
                            </div>

                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">Giảm giá</span>
                                    <Input v-model.number="discountAmount" type="number" min="0" step="1" class="h-8 w-28 text-right tabular-nums outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none" />
                                </div>
                            </div>

                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">Thu khác</span>
                                    <span class="font-medium">{{ surchargeAmount.toLocaleString('vi-VN') }}</span>
                                </div>
                            </div>

                            <div class="space-y-2 border-t border-border/70 pt-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">Khách đưa</span>
                                    <Input v-model.number="amountReceived" type="number" min="0" step="1" class="h-9 w-40 text-right tabular-nums outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none" />
                                </div>

                                <span
                                    v-if="shouldCreateReceivable && grandTotal > 0"
                                    class="inline-flex rounded-md bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-400"
                                >
                                    Tính vào công nợ - {{ grandTotal.toLocaleString('vi-VN') }}
                                </span>

                                <div class="flex flex-wrap gap-2">
                                    <Button v-for="suggestion in moneySuggestions" :key="suggestion" type="button" variant="outline" size="sm" @click="amountReceived = suggestion">
                                        {{ suggestion.toLocaleString('vi-VN') }}
                                    </Button>
                                </div>

                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">Đã thanh toán</span>
                                    <span class="font-semibold">{{ paidByCustomer.toLocaleString('vi-VN') }}</span>
                                </div>

                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">Còn thiếu</span>
                                    <span class="font-semibold" :class="amountRemaining > 0 ? 'text-destructive' : ''">{{ amountRemaining.toLocaleString('vi-VN') }}</span>
                                </div>

                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">Còn thừa</span>
                                    <span class="font-semibold" :class="changeAmount > 0 ? 'text-emerald-600' : ''">{{ changeAmount.toLocaleString('vi-VN') }}</span>
                                </div>
                            </div>

                            <div class="border-t border-border/70 pt-3">
                                <div class="flex items-center justify-between text-2xl font-bold">
                                    <span>{{ posMode === 'RETURN' ? 'Cần trả lại cho khách' : 'Khách cần trả' }}</span>
                                    <span>{{ grandTotal.toLocaleString('vi-VN') }}</span>
                                </div>
                                <div class="mt-2 text-xs text-muted-foreground">
                                    {{ cart.length }} sản phẩm trong hóa đơn
                                </div>
                            </div>
                        </div>

                        <div class="p-4">
                            <Alert v-if="checkoutWarningMessage" variant="destructive" class="mb-3">
                                <AlertTriangle class="size-4" />
                                <AlertTitle>Không thể thanh toán</AlertTitle>
                                <AlertDescription>
                                    {{ checkoutWarningMessage }}
                                </AlertDescription>
                            </Alert>

                            <Button class="h-14 w-full text-lg font-semibold" :disabled="form.processing" @click="submitPos">
                                <ShoppingCart class="size-5" />
                                {{ form.processing
                                    ? 'Đang xử lý...'
                                    : (isEditingOrder
                                        ? 'CẬP NHẬT ĐƠN HÀNG'
                                        : (posMode === 'RETURN'
                                        ? 'Tạo phiếu trả hàng'
                                        : (posMode === 'PREORDER'
                                            ? (preorderStatus === 'DRAFT' ? 'LƯU NHÁP ĐẶT TRƯỚC' : 'TẠO ĐƠN ĐẶT TRƯỚC')
                                            : (shouldCreateReceivable ? 'HOÀN THÀNH ĐƠN & GHI CÔNG NỢ' : (useInvoiceOnSale ? 'THANH TOÁN & XUẤT HÓA ĐƠN' : 'THANH TOÁN & LƯU ĐƠN'))))) }}
                            </Button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </TenantLayout>

    <CustomerFormModal
        v-model:open="customerModalOpen"
        @saved="handleCustomerSaved"
    />

    <SuccessModal
        v-model:open="successModalOpen"
        :total-amount="successModalData.totalAmount"
        :amount-received="successModalData.amountReceived"
        :change-amount="successModalData.changeAmount"
        :item-count="successModalData.itemCount"
        :customer-name="successModalData.customerName"
        :pos-mode="successModalData.createdMode"
        :preorder-status="successModalData.createdPreorderStatus"
        :created-order-ref="successModalData.createdOrderRef"
        @create-new="onCreateNewFromSuccess"
        @view-order="onViewOrderFromSuccess"
    />

    <ProductInfoModal
        v-model:open="productInfoModalOpen"
        :variant="targetVariant"
        :variants="localVariants"
    />

    <ProductPriceHistoryModal
        v-model:open="priceHistoryModalOpen"
        :title="priceHistoryTitle"
        :product-variant-id="targetCartItem?.product_variant_id ?? null"
        :customer-id="selectedCustomerId"
        :sales="props.sales.data"
        @apply="applyHistoryPrice"
    />

    <Dialog :open="itemNoteModalOpen" @update:open="(value) => (itemNoteModalOpen = value)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Ghi chú sản phẩm</DialogTitle>
                <DialogDescription>
                    {{ targetCartItem?.name || '' }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-2">
                <Label>Ghi chú</Label>
                <textarea
                    v-model="itemNoteDraft"
                    rows="4"
                    class="w-full resize-none rounded-md border border-border/70 bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                    placeholder="Nhập ghi chú riêng cho dòng sản phẩm này"
                />
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" @click="itemNoteModalOpen = false">Hủy</Button>
                <Button type="button" @click="applyItemNote">Lưu ghi chú</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <AddProductModal
        v-model:open="addProductModalOpen"
        :categories="props.categories"
        :uoms="props.uoms"
        :warehouses="props.warehouses"
        :default-warehouse-id="selectedWarehouseId"
        :initial-barcode="addProductSeedCode"
        :initial-sku="addProductSeedCode"
        :processing="addProductModalProcessing"
        @submit="handleAddProductSubmit"
    />

    <SelectOrderModal
        :open="selectOrderModalOpen"
        :orders="returnSourceOptions"
        :selected-order-id="returnSourceOrderId"
        @update:open="onSelectOrderModalOpenChange"
        @select="onSelectReturnSourceOrder"
    />
</template>
