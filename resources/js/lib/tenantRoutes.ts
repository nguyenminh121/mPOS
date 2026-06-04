type RouteId = string | number;

const tenantBase = '/tenant';

const asSegment = (value: RouteId) => encodeURIComponent(String(value));

const resource = (name: string) => ({
    index: () => `${tenantBase}/${name}`,
    create: () => `${tenantBase}/${name}/create`,
    store: () => `${tenantBase}/${name}`,
    show: (id: RouteId) => `${tenantBase}/${name}/${asSegment(id)}`,
    edit: (id: RouteId) => `${tenantBase}/${name}/${asSegment(id)}/edit`,
    update: (id: RouteId) => `${tenantBase}/${name}/${asSegment(id)}`,
    destroy: (id: RouteId) => `${tenantBase}/${name}/${asSegment(id)}`,
});

export const tenantRoutes = {
    categories: resource('categories'),
    productCategories: resource('categories'),
    uoms: resource('uoms'),
    warehouses: resource('warehouses'),
    warehouseLocations: resource('warehouse-locations'),
    importReceipts: resource('import-receipts'),
    stocks: resource('stocks'),
    stockTakes: {
        ...resource('stock-takes'),
        complete: (id: RouteId) => `${tenantBase}/stock-takes/${asSegment(id)}/complete`,
        undo: (id: RouteId) => `${tenantBase}/stock-takes/${asSegment(id)}/undo`,
    },
    products: {
        ...resource('products'),
        generateCode: () => `${tenantBase}/products/generate-code`,
        checkExisting: () => `${tenantBase}/products/check-existing`,
        importBulk: () => `${tenantBase}/products/import-bulk`,
        storeBatch: () => `${tenantBase}/products/store-batch`,
    },
    customerGroups: resource('customer-groups'),
    customers: {
        ...resource('customers'),
        addresses: {
            store: (customerId: RouteId) => `${tenantBase}/customers/${asSegment(customerId)}/addresses`,
            update: (customerId: RouteId, addressId: RouteId) => `${tenantBase}/customers/${asSegment(customerId)}/addresses/${asSegment(addressId)}`,
            destroy: (customerId: RouteId, addressId: RouteId) => `${tenantBase}/customers/${asSegment(customerId)}/addresses/${asSegment(addressId)}`,
        },
        debtExport: (customerId: RouteId) => `${tenantBase}/customers/${asSegment(customerId)}/debt-export`,
    },
    priceLists: resource('price-lists'),
    paymentMethodConfigs: resource('payment-method-configs'),
    payments: {
        ...resource('payments'),
        batchDebt: () => `${tenantBase}/payments/batch-debt`,
    },
    surcharges: resource('surcharges'),
    businessExpenses: resource('business-expenses'),
    auditLogs: resource('audit-logs'),
    orders: resource('orders'),
    invoices: resource('invoices'),
    eInvoices: resource('e-invoices'),
    returns: resource('returns'),
    sales: {
        index: () => `${tenantBase}/sales`,
        store: () => `${tenantBase}/sales`,
        preorder: () => `${tenantBase}/sales/preorder`,
        return: () => `${tenantBase}/sales/return`,
    },
    config: {
        codeSequences: {
            index: () => `${tenantBase}/config/code-sequences`,
            store: () => `${tenantBase}/config/code-sequences`,
            update: (config: RouteId) => `${tenantBase}/config/code-sequences/${asSegment(config)}`,
            destroy: (config: RouteId) => `${tenantBase}/config/code-sequences/${asSegment(config)}`,
        },
        stockPolicy: {
            update: () => `${tenantBase}/config/stock-policy`,
        },
        invoicePolicy: {
            update: () => `${tenantBase}/config/invoice-policy`,
        },
    },
};
