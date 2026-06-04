import type { StockWarningOption } from '@/composables/pos/models';

export const getRequiredBaseStock = (quantity: number, conversionRate: number) => {
    return Math.max(0, Math.round(Number(quantity || 0) * Number(conversionRate || 1)));
};

export const buildStockWarning = (
    variantId: number,
    variantName: string,
    requiredBaseStock: number,
    availableBaseStock: number,
): StockWarningOption | null => {
    if (requiredBaseStock <= availableBaseStock) {
        return null;
    }

    return {
        product_variant_id: variantId,
        display_name: variantName,
        required_base_stock: requiredBaseStock,
        available_base_stock: availableBaseStock,
    };
};
