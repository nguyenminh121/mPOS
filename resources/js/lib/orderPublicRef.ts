type OrderPublicRefInput = {
    code?: string | null;
};

export const orderPublicRef = (order: OrderPublicRefInput | null | undefined): string => {
    const code = String(order?.code ?? '').trim();
    if (code !== '') {
        return code;
    }

    return '';
};
