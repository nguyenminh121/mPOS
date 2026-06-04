export const normalizeQuantity = (quantity: number) => {
    return Math.max(1, Math.round(Number(quantity || 1)));
};

export const lineTotal = (price: number, quantity: number) => {
    return Number(price || 0) * Number(quantity || 0);
};
