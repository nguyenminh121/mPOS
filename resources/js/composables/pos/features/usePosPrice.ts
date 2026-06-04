import { computed } from 'vue';
import type { ComputedRef, Ref, WritableComputedRef } from 'vue';
import { calculateGrandTotal, calculateSubtotal, calculateSurchargeAmount } from '@/composables/pos/core/price';
import type { CartItem, PriceListOption, VariantOption, VariantUnitOption } from '@/composables/pos/models';
import { buildVariantDisplayName, filterAndRankProductVariants, normalizeProductSearchText } from '@/composables/products/productSearch';

type UsePosPriceParams = {
    localVariants: Ref<VariantOption[]>;
    selectedPriceList: ComputedRef<PriceListOption | null>;
    searchQuery: WritableComputedRef<string>;
};

export const usePosPrice = ({ localVariants, selectedPriceList, searchQuery }: UsePosPriceParams) => {
    const normalizeSearchText = normalizeProductSearchText;

    const getVariantById = (variantId: number) => {
        return localVariants.value.find((variant) => variant.id === variantId) ?? null;
    };

    const getVariantUnitOptions = (variant?: VariantOption | null): VariantUnitOption[] => {
        const options: VariantUnitOption[] = [];

        const pushOption = (name: string, conversionRate: number) => {
            const trimmedName = name.trim();
            if (trimmedName === '') return;

            const existed = options.some((item) => item.name.toLowerCase() === trimmedName.toLowerCase());
            if (existed) return;

            options.push({
                name: trimmedName,
                conversion_rate: Number(conversionRate) > 0 ? Number(conversionRate) : 1,
            });
        };

        pushOption(variant?.base_unit ?? 'Cái', 1);

        if (options[0]) {
            options[0].retail_price = Number(variant?.price ?? 0);
        }

        for (const option of variant?.unit_options ?? []) {
            pushOption(option.name, option.conversion_rate ?? 1);

            const current = options.find((item) => item.name.toLowerCase() === option.name.trim().toLowerCase());
            if (current && option.retail_price !== undefined && option.retail_price !== null) {
                current.retail_price = Number(option.retail_price);
            }
        }

        return options;
    };

    const normalizeCartItem = (item: CartItem): CartItem => {
        const variant = localVariants.value.find((variantItem) => variantItem.id === item.product_variant_id);
        const unitOptions = getVariantUnitOptions(variant);

        const matchedUnit = unitOptions.find((option) => option.name === item.unit_name);
        const fallbackUnit = matchedUnit ?? unitOptions[0] ?? { name: item.unit_name || 'Cái', conversion_rate: 1 };

        return {
            ...item,
            unit_name: fallbackUnit.name,
            conversion_rate: Number(item.conversion_rate || fallbackUnit.conversion_rate || 1),
            unit_options: unitOptions.length > 0 ? unitOptions : [fallbackUnit],
            note: item.note ?? '',
        };
    };

    const filteredVariants = computed(() => {
        return filterAndRankProductVariants(localVariants.value, searchQuery.value, 10);
    });

    const hasSearchQuery = computed(() => normalizeSearchText(searchQuery.value).length > 0);

    const variantPriceMap = computed(() => {
        return new Map(localVariants.value.map((variant) => [variant.id, Number(variant.price || 0)]));
    });

    const getUnitConversionRate = (variantId: number, unitName: string) => {
        const variant = getVariantById(variantId);
        if (!variant) return 1;

        const unit = getVariantUnitOptions(variant).find((item) => item.name === unitName);
        return Number(unit?.conversion_rate || 1);
    };

    const resolvePriceForVariant = (variantId: number, unitName: string, quantity: number) => {
        const basePrice = variantPriceMap.value.get(variantId) ?? 0;
        const conversionRate = getUnitConversionRate(variantId, unitName);
        const variant = getVariantById(variantId);
        const selectedUnit = getVariantUnitOptions(variant).find((item) => item.name === unitName);
        const unitRetailPrice = selectedUnit?.retail_price;

        const fallback = unitRetailPrice !== undefined && unitRetailPrice !== null
            ? Number(unitRetailPrice || 0)
            : Number(basePrice || 0) * Number(conversionRate || 1);

        if (!selectedPriceList.value) {
            return fallback;
        }

        const matchedRules = selectedPriceList.value.items
            .filter((item) => item.product_variant_id === variantId)
            .filter((item) => item.unit_name === unitName)
            .filter((item) => item.min_quantity <= quantity)
            .sort((left, right) => right.min_quantity - left.min_quantity);

        const matched = matchedRules[0];
        if (!matched) {
            return fallback;
        }

        return Number(matched.price || fallback);
    };

    const getUnitPriceDisplay = (variantId: number, unitName: string, quantity = 1) => {
        const price = resolvePriceForVariant(variantId, unitName, quantity);
        return Number(price || 0).toLocaleString('vi-VN');
    };

    return {
        normalizeSearchText,
        buildVariantDisplayName,
        getVariantById,
        getVariantUnitOptions,
        normalizeCartItem,
        filteredVariants,
        hasSearchQuery,
        resolvePriceForVariant,
        getUnitPriceDisplay,
        calculateSubtotal,
        calculateSurchargeAmount,
        calculateGrandTotal,
    };
};
