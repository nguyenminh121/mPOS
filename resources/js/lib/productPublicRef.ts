type ProductPublicRefInput = {
    id?: number | string | null;
    sku?: string | null;
    barcode?: string | null;
};

export const productPublicRef = (product: ProductPublicRefInput | null | undefined): string | number => {
    const sku = String(product?.sku ?? '').trim();
    if (sku !== '') {
        return sku;
    }

    const barcode = String(product?.barcode ?? '').trim();
    if (barcode !== '') {
        return barcode;
    }

    if (typeof product?.id === 'number' || typeof product?.id === 'string') {
        return product.id;
    }

    return '';
};
