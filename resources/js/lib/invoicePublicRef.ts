type InvoicePublicRefInput = {
    code?: string | null;
};

export const invoicePublicRef = (invoice: InvoicePublicRefInput | null | undefined): string => {
    const code = String(invoice?.code ?? '').trim();
    if (code !== '') {
        return code;
    }

    return '';
};
