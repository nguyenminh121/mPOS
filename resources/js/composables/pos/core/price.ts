import type { SurchargeOption } from '@/composables/pos/models';

export const calculateSubtotal = (items: Array<{ price: number; quantity: number }>) => {
    return items.reduce((sum, item) => sum + Number(item.price || 0) * Number(item.quantity || 0), 0);
};

export const calculateSurchargeAmount = (subtotal: number, surcharge: SurchargeOption | null) => {
    if (!surcharge) return 0;

    if (surcharge.type === 'PERCENT') {
        const amount = (Number(subtotal || 0) * Number(surcharge.value || 0)) / 100;
        return Math.max(0, Number(amount.toFixed(2)));
    }

    return Math.max(0, Number(surcharge.value || 0));
};

export const calculateGrandTotal = (subtotal: number, discount: number, surchargeAmount: number) => {
    return Math.max(0, Number(subtotal || 0) - Number(discount || 0) + Number(surchargeAmount || 0));
};
