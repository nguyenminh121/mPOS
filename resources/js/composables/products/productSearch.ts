import type { VariantOption } from '@/composables/pos/models';

export type ProductSearchableVariant = Pick<VariantOption, 'id' | 'product_name' | 'name' | 'sku' | 'barcode'>;

type SearchIndex = {
    productName: string;
    variantName: string;
    sku: string;
    barcode: string;
    fullText: string;
};

export const normalizeProductSearchText = (value: string): string => {
    return value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/[^a-z0-9\s]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
};

export const buildVariantDisplayName = (productName?: string | null, variantName?: string | null): string => {
    const product = (productName ?? '').trim();
    const variant = (variantName ?? '').trim();

    if (product === '' && variant === '') return '';
    if (product === '') return variant;
    if (variant === '' || variant.toLowerCase() === product.toLowerCase()) return product;

    return `${product} - ${variant}`;
};

const buildSearchIndex = (variant: ProductSearchableVariant): SearchIndex => {
    const productName = normalizeProductSearchText(variant.product_name ?? '');
    const variantName = normalizeProductSearchText(variant.name ?? '');
    const sku = normalizeProductSearchText(variant.sku ?? '');
    const barcode = normalizeProductSearchText(variant.barcode ?? '');

    return {
        productName,
        variantName,
        sku,
        barcode,
        fullText: normalizeProductSearchText(`${variant.product_name ?? ''} ${variant.name ?? ''} ${variant.sku ?? ''} ${variant.barcode ?? ''}`),
    };
};

const computeMatchScore = (index: SearchIndex, normalizedQuery: string, tokens: string[]): number => {
    let score = 0;

    if (index.productName === normalizedQuery) score += 1000;
    if (index.variantName === normalizedQuery) score += 950;

    if (index.productName.startsWith(normalizedQuery)) score += 800;
    if (index.variantName.startsWith(normalizedQuery)) score += 760;

    if (index.productName.includes(normalizedQuery)) score += 520;
    if (index.variantName.includes(normalizedQuery)) score += 500;

    if (index.sku === normalizedQuery) score += 420;
    if (index.barcode === normalizedQuery) score += 400;

    if (index.sku.startsWith(normalizedQuery)) score += 300;
    if (index.barcode.startsWith(normalizedQuery)) score += 280;

    if (index.sku.includes(normalizedQuery)) score += 160;
    if (index.barcode.includes(normalizedQuery)) score += 140;

    for (const token of tokens) {
        if (index.productName.startsWith(token)) score += 24;
        if (index.variantName.startsWith(token)) score += 20;
        if (index.productName.includes(token)) score += 12;
        if (index.variantName.includes(token)) score += 10;
        if (index.sku.includes(token)) score += 6;
        if (index.barcode.includes(token)) score += 5;
    }

    const earliestTokenIndex = tokens.reduce((bestIndex, token) => {
        const currentIndex = index.fullText.indexOf(token);
        if (currentIndex < 0) return bestIndex;
        if (bestIndex < 0) return currentIndex;
        return Math.min(bestIndex, currentIndex);
    }, -1);

    if (earliestTokenIndex >= 0) {
        score += Math.max(0, 40 - earliestTokenIndex);
    }

    return score;
};

export const filterAndRankProductVariants = <T extends ProductSearchableVariant>(
    variants: T[],
    query: string,
    limit = 30,
): T[] => {
    const normalizedQuery = normalizeProductSearchText(query);
    const tokens = normalizedQuery.split(' ').filter((token) => token.length > 0);

    if (tokens.length === 0) {
        return [];
    }

    return variants
        .map((variant) => {
            const index = buildSearchIndex(variant);
            const matched = tokens.every((token) => index.fullText.includes(token));
            const score = matched ? computeMatchScore(index, normalizedQuery, tokens) : Number.NEGATIVE_INFINITY;

            return {
                variant,
                index,
                matched,
                score,
                displayName: buildVariantDisplayName(variant.product_name, variant.name),
            };
        })
        .filter((row) => row.matched)
        .sort((left, right) => {
            if (left.score !== right.score) return right.score - left.score;

            const byName = left.displayName.localeCompare(right.displayName, 'vi');
            if (byName !== 0) return byName;

            return left.variant.id - right.variant.id;
        })
        .slice(0, limit)
        .map((row) => row.variant);
};
