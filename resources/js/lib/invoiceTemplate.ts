export type InvoiceTemplateCustomer = {
    name: string;
    phone: string | null;
    address: string | null;
    taxCode: string | null;
    companyName: string | null;
    email: string | null;
};

export type InvoiceTemplateItem = {
    id: number | null;
    productName: string;
    unitName: string;
    quantity: number;
    price: number;
    discount: number;
    total: number;
};

export type InvoiceTemplateModel = {
    id: number;
    code: string;
    status: string;
    saleAt: string | null;
    subtotal: number;
    taxRate: number;
    taxAmount: number;
    finalAmount: number;
    discount: number;
    surcharge: number;
    paidAmount: number;
    changeAmount: number;
    customer: InvoiceTemplateCustomer;
    createdByUser: { fullName: string };
    items: InvoiceTemplateItem[];
};

const UTC7_TIMEZONE = 'Asia/Ho_Chi_Minh';

const toNumber = (value: unknown, fallback = 0): number => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
};

const parseDateInput = (value?: string | null): Date | null => {
    if (!value) return null;

    if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value)) {
        return new Date(`${value}:00+07:00`);
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return null;

    return parsed;
};

const normalizeWalkInCustomerName = (value: unknown): string => {
    const raw = String(value ?? '').trim();
    if (raw === '') return 'Khách lẻ';

    const normalized = raw
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/\s+/g, ' ')
        .trim();

    if (normalized === 'khach le') return 'Khách lẻ';

    return raw;
};

export const toUtc7DateTimeInput = (value?: string | null): string => {
    const date = parseDateInput(value) ?? new Date();

    const formatter = new Intl.DateTimeFormat('sv-SE', {
        timeZone: UTC7_TIMEZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });

    return formatter.format(date).replace(' ', 'T');
};

export const toUtc7IsoString = (value: string | null): string | null => {
    if (!value) return null;
    const trimmed = value.trim();
    if (trimmed === '') return null;

    if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(trimmed)) {
        return `${trimmed}:00+07:00`;
    }

    return trimmed;
};

export const parseUtc7DateTimeToMs = (value: string | null | undefined): number | null => {
    const date = parseDateInput(value ?? null);
    if (!date) return null;
    return date.getTime();
};

export const mapInvoiceToTemplate = (invoice: any): InvoiceTemplateModel => {
    const items: InvoiceTemplateItem[] = Array.isArray(invoice?.items)
        ? invoice.items.map((item: any) => ({
              id: item?.id ?? null,
              productName: String(item?.product_name ?? item?.productName ?? ''),
              unitName: String(item?.unit_name ?? item?.unitName ?? 'Cai'),
              quantity: toNumber(item?.quantity, 0),
              price: toNumber(item?.price, 0),
              discount: toNumber(item?.discount, 0),
              total: toNumber(item?.total, 0),
          }))
        : [];

    const subtotal = toNumber(invoice?.subtotal, items.reduce((sum, item) => sum + item.total, 0));
    const taxRate = toNumber(invoice?.tax_rate, 0);
    const taxAmount = toNumber(invoice?.tax_amount, (subtotal * taxRate) / 100);
    const finalAmount = toNumber(invoice?.total_amount, subtotal + taxAmount);

    return {
        id: toNumber(invoice?.id, 0),
        code: String(invoice?.code ?? ''),
        status: String(invoice?.status ?? 'ISSUED'),
        saleAt: invoice?.sale_at ?? null,
        subtotal,
        taxRate,
        taxAmount,
        finalAmount,
        discount: toNumber(invoice?.discount, 0),
        surcharge: toNumber(invoice?.surcharge, 0),
        paidAmount: toNumber(invoice?.paid_amount ?? invoice?.paidAmount, finalAmount),
        changeAmount: toNumber(invoice?.change_amount ?? invoice?.changeAmount, 0),
        customer: {
            name: normalizeWalkInCustomerName(invoice?.customer?.name),
            phone: invoice?.customer?.phone ?? null,
            address: invoice?.customer?.address ?? null,
            taxCode: invoice?.customer?.tax_code ?? invoice?.customer?.taxCode ?? null,
            companyName: invoice?.customer?.company_name ?? invoice?.customer?.companyName ?? null,
            email: invoice?.customer?.email ?? null,
        },
        createdByUser: {
            fullName: String(
                invoice?.issuer_unit_name
                ?? invoice?.issuerUnitName
                ?? invoice?.creator?.name
                ?? invoice?.createdByUser?.fullName
                ?? 'N/A',
            ),
        },
        items,
    };
};

export const formatCurrencyVnd = (value: number): string => {
    return new Intl.NumberFormat('vi-VN').format(toNumber(value, 0));
};

export const formatDateTimeVn = (value: string | null): string => {
    if (!value) return '-';

    const date = parseDateInput(value);
    if (!date) return '-';

    return new Intl.DateTimeFormat('vi-VN', {
        timeZone: UTC7_TIMEZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
};

export const formatDateTimeVnWithZone = (value: string | null): string => {
    const formatted = formatDateTimeVn(value);
    if (formatted === '-') return '-';
    return formatted;
};

export const invoiceStatusLabel = (status: string): string => {
    if (status === 'ISSUED') return 'Đã phát hành';
    if (status === 'REPLACED') return 'Đã thay thế';
    if (status === 'CANCELLED') return 'Đã hủy';
    return status;
};