import type { Ref, WritableComputedRef } from 'vue';
import type { AddProductPayload, CustomerOption, VariantOption, WarehouseOption } from '@/composables/pos/models';
import { tenantRoutes } from '@/lib/tenantRoutes';

type UsePosCatalogActionsParams = {
    addProductModalOpen: Ref<boolean>;
    addProductModalProcessing: Ref<boolean>;
    addProductSeedCode: Ref<string>;
    customerModalOpen: Ref<boolean>;
    customerSearchQuery: Ref<string>;
    localVariants: Ref<VariantOption[]>;
    localCustomers: Ref<CustomerOption[]>;
    searchQuery: WritableComputedRef<string>;
    selectedCustomerId: WritableComputedRef<number | null>;
    warehouses: WarehouseOption[];
    addToCart: (variant: VariantOption) => void;
    onAddProductSuccess?: () => void;
};

export const usePosCatalogActions = ({
    addProductModalOpen,
    addProductModalProcessing,
    addProductSeedCode,
    customerModalOpen,
    customerSearchQuery,
    localVariants,
    localCustomers,
    searchQuery,
    selectedCustomerId,
    warehouses,
    addToCart,
    onAddProductSuccess,
}: UsePosCatalogActionsParams) => {
    const openAddProductModalFromSearch = () => {
        addProductSeedCode.value = searchQuery.value.trim();
        addProductModalOpen.value = true;
    };

    const csrfToken = () => {
        const metaToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
        if (metaToken !== '') {
            return metaToken;
        }

        const xsrfCookie = document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='));

        if (!xsrfCookie) {
            return '';
        }

        return decodeURIComponent(xsrfCookie.split('=')[1] ?? '');
    };

    const handleAddProductSubmit = async (payload: AddProductPayload) => {
        addProductModalProcessing.value = true;
        let createdVariantForCart: VariantOption | null = null;

        const token = csrfToken();
        const formData = new FormData();

        formData.append('name', payload.name);
        formData.append('sku', payload.sku);
        formData.append('barcode', payload.barcode);
        formData.append('base_unit', payload.base_unit);
        formData.append('is_active', '1');

        if (payload.category_id !== null) {
            formData.append('category_id', String(payload.category_id));
        }

        formData.append('initial_stock', String(Number(payload.initial_stock || 0)));
        if (payload.warehouse_id !== null) {
            formData.append('warehouse_id', String(payload.warehouse_id));
        }

        formData.append('variants[0][name]', payload.variant_name ?? payload.name);
        formData.append('variants[0][sku]', payload.sku);
        formData.append('variants[0][barcode]', payload.barcode);
        formData.append('variants[0][base_unit]', payload.base_unit);
        formData.append('variants[0][price]', String(Number(payload.retail_price || 0)));
        formData.append('variants[0][cost_price]', String(Number(payload.cost_price || 0)));
        formData.append('variants[0][wholesale_price]', String(Number(payload.wholesale_price || 0)));
        formData.append('variants[0][currency]', 'VND');
        formData.append('variants[0][is_active]', '1');
        formData.append('variants[0][is_default]', '1');

        if (payload.image_file) {
            formData.append('images[0][_file]', payload.image_file);
            formData.append('images[0][is_default]', '1');
        }

        if (token !== '') {
            formData.append('_token', token);
        }

        try {
            const response = await fetch(tenantRoutes.products.store(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                },
                body: formData,
            });

            const body = await response.json().catch(() => null);

            if (!response.ok) {
                const message = typeof body?.message === 'string' ? body.message : 'Không thể tạo sản phẩm mới.';
                alert(message);
                return;
            }

            const createdVariant = body?.product?.variant;
            const createdProductName = body?.product?.name ?? payload.name;

            if (createdVariant && typeof createdVariant.id === 'number') {
                const newVariant: VariantOption = {
                    id: Number(createdVariant.id),
                    product_id: Number(body?.product?.id ?? 0) || null,
                    product_name: createdProductName,
                    name: createdVariant.name ?? payload.name,
                    sku: createdVariant.sku ?? payload.sku,
                    barcode: createdVariant.barcode ?? payload.barcode,
                    base_unit: createdVariant.base_unit ?? payload.base_unit,
                    price: Number(createdVariant.price ?? payload.retail_price ?? 0),
                    unit_options: [],
                    total_stock: Number(payload.initial_stock || 0),
                    stock_by_warehouse: payload.warehouse_id
                        ? [{
                            warehouse_id: payload.warehouse_id,
                            warehouse_name: warehouses.find((item) => item.id === payload.warehouse_id)?.name,
                            quantity: Number(payload.initial_stock || 0),
                        }]
                        : [],
                };

                const existedIndex = localVariants.value.findIndex((item) => item.id === newVariant.id);
                if (existedIndex >= 0) {
                    localVariants.value[existedIndex] = newVariant;
                } else {
                    localVariants.value.unshift(newVariant);
                }

                createdVariantForCart = newVariant;
            }

            addProductModalOpen.value = false;
            searchQuery.value = '';

            if (createdVariantForCart) {
                addToCart(createdVariantForCart);
            }

            onAddProductSuccess?.();
        } finally {
            addProductModalProcessing.value = false;
        }
    };

    const openCustomerModal = () => {
        customerModalOpen.value = true;
    };

    const handleCustomerSaved = (customer: CustomerOption & { is_active?: boolean }) => {
        if (customer.is_active === false) {
            return;
        }

        const existedIndex = localCustomers.value.findIndex((item) => item.id === customer.id);
        if (existedIndex >= 0) {
            localCustomers.value[existedIndex] = {
                id: customer.id,
                code: customer.code ?? null,
                name: customer.name,
                phone: customer.phone ?? null,
                price_list_id: customer.price_list_id ?? null,
            };
        } else {
            localCustomers.value.unshift({
                id: customer.id,
                code: customer.code ?? null,
                name: customer.name,
                phone: customer.phone ?? null,
                price_list_id: customer.price_list_id ?? null,
            });
        }

        selectedCustomerId.value = customer.id;
        customerSearchQuery.value = '';
    };

    return {
        openAddProductModalFromSearch,
        handleAddProductSubmit,
        openCustomerModal,
        handleCustomerSaved,
    };
};
