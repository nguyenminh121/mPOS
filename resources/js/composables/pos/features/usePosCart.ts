import { computed, ref    } from 'vue';
import type {ComputedRef, Ref, WritableComputedRef} from 'vue';
import { getRequiredBaseStock } from '@/composables/pos/core/stock';
import type { CartItem, PosDraftTab, StockWarningOption, VariantOption, VariantUnitOption } from '@/composables/pos/models';

type HistoryPricePayload = { price: number; quantity: number; unit_name: string; conversion_rate: number };

type UsePosCartParams = {
    mode: Ref<'RETAIL' | 'PREORDER' | 'RETURN'>;
    activeTab: ComputedRef<PosDraftTab | null>;
    cart: ComputedRef<CartItem[]>;
    searchQuery: WritableComputedRef<string>;
    localVariants: Ref<VariantOption[]>;
    selectedWarehouseId: ComputedRef<number | null>;
    allowNegativeStock: ComputedRef<boolean>;
    grandTotal: ComputedRef<number>;
    resolvePriceForVariant: (variantId: number, unitName: string, quantity: number) => number;
    getVariantById: (variantId: number) => VariantOption | null;
    getVariantUnitOptions: (variant?: VariantOption | null) => VariantUnitOption[];
    buildVariantDisplayName: (productName?: string | null, variantName?: string | null) => string;
};

export const usePosCart = ({
    mode,
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
}: UsePosCartParams) => {
    const productInfoModalOpen = ref(false);
    const priceHistoryModalOpen = ref(false);
    const itemNoteModalOpen = ref(false);
    const targetCartIndex = ref<number | null>(null);
    const itemNoteDraft = ref('');

    const targetCartItem = computed<CartItem | null>(() => {
        if (targetCartIndex.value === null) return null;
        return cart.value[targetCartIndex.value] ?? null;
    });

    const targetVariant = computed<VariantOption | null>(() => {
        if (!targetCartItem.value) return null;
        return localVariants.value.find((item) => item.id === targetCartItem.value?.product_variant_id) ?? null;
    });

    const priceHistoryTitle = computed(() => {
        if (!targetCartItem.value) return 'Giá bán gần đây';
        return `Giá bán gần đây - ${targetCartItem.value.name}`;
    });

    const getVariantAvailableBaseStock = (variantId: number) => {
        const variant = getVariantById(variantId);
        if (!variant) return 0;

        if (selectedWarehouseId.value === null) {
            return Math.floor(Number(variant.total_stock || 0));
        }

        const stockInWarehouse = variant.stock_by_warehouse?.find((row) => Number(row.warehouse_id) === Number(selectedWarehouseId.value));
        return Math.floor(Number(stockInWarehouse?.quantity || 0));
    };

    const getStockWarningForVariant = (variantId: number, requiredBaseStock: number, variantName?: string): StockWarningOption | null => {
        if (mode.value === 'RETURN') {
            return null;
        }

        if (allowNegativeStock.value) {
            return null;
        }

        const availableBaseStock = getVariantAvailableBaseStock(variantId);
        if (requiredBaseStock <= availableBaseStock) {
            return null;
        }

        return {
            product_variant_id: variantId,
            display_name: variantName || getVariantById(variantId)?.name || `Biến thể #${variantId}`,
            required_base_stock: requiredBaseStock,
            available_base_stock: availableBaseStock,
        };
    };

    const getItemStockWarning = (item: CartItem): StockWarningOption | null => {
        const requiredBaseStock = getRequiredBaseStock(item.quantity, item.conversion_rate);
        return getStockWarningForVariant(item.product_variant_id, requiredBaseStock, item.name);
    };

    const isItemOutOfStock = (item: CartItem) => {
        return getItemStockWarning(item) !== null;
    };

    const stockWarnings = computed(() => {
        if (allowNegativeStock.value) {
            return [];
        }

        const byVariant = new Map<number, StockWarningOption>();

        for (const item of cart.value) {
            const warning = getItemStockWarning(item);
            if (!warning) {
                continue;
            }

            byVariant.set(warning.product_variant_id, warning);
        }

        return Array.from(byVariant.values()) as StockWarningOption[];
    });

    const addToCart = (variant: VariantOption) => {
        const tab = activeTab.value;
        if (!tab) return;

        const unitOptions = getVariantUnitOptions(variant);
        const defaultUnit = unitOptions[0] ?? { name: variant.base_unit || 'Cái', conversion_rate: 1 };

        const existed = tab.items.find((item) => item.product_variant_id === variant.id);
        if (existed) {
            existed.quantity += 1;
            existed.price = resolvePriceForVariant(existed.product_variant_id, existed.unit_name, existed.quantity);
            return;
        }

        const unitName = defaultUnit.name;
        const unitPrice = resolvePriceForVariant(variant.id, unitName, 1);

        tab.items.push({
            product_variant_id: variant.id,
            name: buildVariantDisplayName(variant.product_name, variant.name),
            sku: variant.sku,
            unit_name: unitName,
            conversion_rate: Number(defaultUnit.conversion_rate || 1),
            unit_options: unitOptions,
            price: Number(unitPrice || 0),
            quantity: 1,
            note: '',
        });

        searchQuery.value = '';
    };

    const increaseQty = (index: number) => {
        const tab = activeTab.value;
        if (!tab || !tab.items[index]) return;

        if (mode.value === 'RETURN') {
            const maxReturnable = Math.max(0, Number(tab.items[index].sold_quantity || 0));
            if (Number(tab.items[index].quantity || 0) >= maxReturnable) {
                return;
            }
        }

        tab.items[index].quantity += 1;
        tab.items[index].price = resolvePriceForVariant(tab.items[index].product_variant_id, tab.items[index].unit_name, tab.items[index].quantity);
    };

    const decreaseQty = (index: number) => {
        const tab = activeTab.value;
        if (!tab || !tab.items[index]) return;

        if (mode.value === 'RETURN') {
            tab.items[index].quantity = Math.max(0, Number(tab.items[index].quantity || 0) - 1);
            tab.items[index].price = resolvePriceForVariant(tab.items[index].product_variant_id, tab.items[index].unit_name, tab.items[index].quantity);
            return;
        }

        if (tab.items[index].quantity <= 1) {
            tab.items.splice(index, 1);
            return;
        }

        tab.items[index].quantity -= 1;
        tab.items[index].price = resolvePriceForVariant(tab.items[index].product_variant_id, tab.items[index].unit_name, tab.items[index].quantity);
    };

    const removeItem = (index: number) => {
        const tab = activeTab.value;
        if (!tab) return;
        tab.items.splice(index, 1);
    };

    const updateItemUnit = (index: number, unitName: string) => {
        const tab = activeTab.value;
        if (!tab || !tab.items[index]) return;

        const selected = tab.items[index].unit_options.find((unit) => unit.name === unitName);
        if (!selected) return;

        tab.items[index].unit_name = selected.name;
        tab.items[index].conversion_rate = Number(selected.conversion_rate || 1);
        tab.items[index].price = resolvePriceForVariant(tab.items[index].product_variant_id, selected.name, tab.items[index].quantity);
    };

    const updateItemPrice = (index: number, value: number | string) => {
        const tab = activeTab.value;
        if (!tab || !tab.items[index]) return;

        const parsed = Number(value);
        tab.items[index].price = Number.isFinite(parsed) && parsed >= 0 ? parsed : 0;
    };

    const openProductInfo = (index: number) => {
        targetCartIndex.value = index;
        productInfoModalOpen.value = true;
    };

    const openPriceHistory = (index: number) => {
        targetCartIndex.value = index;
        priceHistoryModalOpen.value = true;
    };

    const openItemNote = (index: number) => {
        targetCartIndex.value = index;
        itemNoteDraft.value = cart.value[index]?.note ?? '';
        itemNoteModalOpen.value = true;
    };

    const applyItemNote = () => {
        const tab = activeTab.value;
        if (!tab || targetCartIndex.value === null || !tab.items[targetCartIndex.value]) {
            itemNoteModalOpen.value = false;
            return;
        }

        tab.items[targetCartIndex.value].note = itemNoteDraft.value.trim();
        itemNoteModalOpen.value = false;
    };

    const applyHistoryPrice = (payload: HistoryPricePayload) => {
        const tab = activeTab.value;
        if (!tab || targetCartIndex.value === null || !tab.items[targetCartIndex.value]) return;

        const currentItem = tab.items[targetCartIndex.value];
        const selectedUnit = currentItem.unit_options.find((item) => item.name === payload.unit_name);

        const nextQuantity = Math.max(1, Math.round(Number(payload.quantity || 1)));
        currentItem.quantity = nextQuantity;

        currentItem.price = Number(payload.price || 0);
        if (selectedUnit) {
            currentItem.unit_name = selectedUnit.name;
            currentItem.conversion_rate = Number(selectedUnit.conversion_rate || 1);
        } else {
            currentItem.unit_name = payload.unit_name;
            currentItem.conversion_rate = Number(payload.conversion_rate || 1);
        }

        priceHistoryModalOpen.value = false;
    };

    const clearAllItems = () => {
        const tab = activeTab.value;
        if (!tab) return;
        tab.items = [];
        tab.amount_received = grandTotal.value;
    };

    return {
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
    };
};
