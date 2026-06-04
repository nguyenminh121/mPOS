import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import type { PosDraftTab, SaleOption, SurchargeOption } from '@/composables/pos/models';
import { tenantRoutes } from '@/lib/tenantRoutes';

type PosMode = 'RETAIL' | 'PREORDER' | 'RETURN';
type PreorderStatus = 'PREORDER' | 'DRAFT';

type PosSuccessModalData = {
    totalAmount: number;
    amountReceived: number;
    changeAmount: number;
    itemCount: number;
    customerName: string;
    createdOrderRef: string | number | null;
    createdMode: 'RETAIL' | 'PREORDER' | 'RETURN';
    createdPreorderStatus: 'PREORDER' | 'DRAFT';
};

type PosSaleForm = {
    customer_id: number | null;
    seller_id: number | null;
    warehouse_id: number | null;
    status: 'CONFIRMED' | 'DRAFT' | 'COMPLETED' | 'PREORDER';
    create_receivable: boolean;
    paid_amount: number;
    replace_invoice: boolean;
    revision_reason: string;
    order_date: string;
    type: 'RETAIL' | 'WHOLESALE' | 'RETURN';
    discount: number;
    note: string;
    items: Array<{
        product_variant_id: number;
        unit_name: string;
        conversion_rate: number;
        quantity: number;
        price: number;
        discount: number;
        tax_rate: number;
        note: string;
    }>;
    surcharges: Array<{
        name: string;
        type: 'PERCENT' | 'FIXED_AMOUNT';
        value: number;
        amount: number;
        is_refundable: boolean;
    }>;
    post: (
        url: string,
        options: {
            preserveState: boolean;
            preserveScroll: boolean;
            onError: (errors: Record<string, string>) => void;
            onSuccess: (page?: unknown) => void;
        }
    ) => void;
    put: (
        url: string,
        options: {
            preserveState: boolean;
            preserveScroll: boolean;
            onError: (errors: Record<string, string>) => void;
            onSuccess: (page?: unknown) => void;
        }
    ) => void;
};

type UsePosParams = {
    mode: Ref<PosMode>;
    preorderStatus: Ref<PreorderStatus>;
    returnSourceOrderId: Ref<number | null>;
    activeTab: ComputedRef<PosDraftTab | null>;
    selectedWarehouseId: ComputedRef<number | null>;
    selectedSellerId: ComputedRef<number | null>;
    selectedType: ComputedRef<'RETAIL' | 'WHOLESALE'>;
    selectedSurcharge: ComputedRef<SurchargeOption | null>;
    selectedCustomerName: ComputedRef<string>;
    shouldCreateReceivable: ComputedRef<boolean>;
    amountReceived: ComputedRef<number>;
    surchargeAmount: ComputedRef<number>;
    grandTotal: ComputedRef<number>;
    checkoutWarningMessage: Ref<string>;
    successModalData: Ref<PosSuccessModalData>;
    successModalOpen: Ref<boolean>;
    form: PosSaleForm;
    sales: ComputedRef<SaleOption[]>;
    onSuccessReset?: () => void;
    editingOrderRef?: ComputedRef<string>;
    editingOrderStatus?: ComputedRef<string>;
    editingOrderHasInvoice?: ComputedRef<boolean>;
    useInvoiceOnSale?: ComputedRef<boolean>;
};

export const usePos = ({
    mode,
    preorderStatus,
    returnSourceOrderId,
    activeTab,
    selectedWarehouseId,
    selectedSellerId,
    selectedType,
    selectedSurcharge,
    selectedCustomerName,
    shouldCreateReceivable,
    amountReceived,
    surchargeAmount,
    grandTotal,
    checkoutWarningMessage,
    successModalData,
    successModalOpen,
    form,
    sales,
    onSuccessReset,
    editingOrderRef,
    editingOrderStatus,
    editingOrderHasInvoice,
    useInvoiceOnSale,
}: UsePosParams) => {
    const isEditingOrder = computed(() => {
        const ref = String(editingOrderRef?.value ?? '').trim();
        return ref !== '';
    });
    const isReturnableOrder = (status: string | null | undefined) => {
        return String(status ?? '').trim().toUpperCase() === 'COMPLETED';
    };

    const hasRemainingReturnableItems = (order: SaleOption) => {
        const items = Array.isArray(order.items) ? order.items : [];

        return items.some((item) => {
            const sold = Math.max(0, Math.round(Number(item.quantity ?? 0)));
            const returned = Math.max(0, Math.round(Number(item.returned_quantity ?? 0)));

            return sold > returned;
        });
    };

    const returnSourceOptions = computed(() => {
        return sales.value.filter((item): item is SaleOption & { id: number; code: string } => {
            return typeof item.id === 'number'
                && String(item.code ?? '').trim() !== ''
                && isReturnableOrder(item.status)
                && hasRemainingReturnableItems(item);
        });
    });

    const resolveCheckoutErrorMessage = (errors: Record<string, string>) => {
        const priorityKeys = [
            'items',
            'warehouse_id',
            'customer_id',
            'seller_id',
            'order_date',
            'paid_amount',
            'order_id',
        ];

        for (const key of priorityKeys) {
            const message = errors[key];
            if (typeof message === 'string' && message.trim() !== '') {
                return message;
            }
        }

        const firstKey = Object.keys(errors)[0];
        if (!firstKey) {
            return 'Không thể xử lý đơn. Vui lòng kiểm tra dữ liệu và thử lại.';
        }

        return errors[firstKey] || 'Không thể xử lý đơn. Vui lòng kiểm tra dữ liệu và thử lại.';
    };

    const createSuccessSnapshot = () => {
        const tab = activeTab.value;

        return {
            totalAmount: Number(grandTotal.value || 0),
            amountReceived: mode.value === 'RETURN' ? 0 : Number(amountReceived.value || 0),
            changeAmount: mode.value === 'RETURN'
                ? 0
                : Math.max(0, Number(amountReceived.value || 0) - Number(grandTotal.value || 0)),
            itemCount: tab?.items.length ?? 0,
            customerName: selectedCustomerName.value || 'Khách lẻ',
            createdOrderRef: null,
            createdMode: mode.value,
            createdPreorderStatus: preorderStatus.value,
        };
    };

    const resolveSuccessSnapshot = (base: PosSuccessModalData, page?: unknown): PosSuccessModalData => {
        const props = (page as { props?: Record<string, unknown> } | undefined)?.props;
        const flash = (props?.flash as Record<string, unknown> | undefined) ?? undefined;

        const createdOrderIdRaw = flash?.created_order_id;
        const createdOrderCodeRaw = String(flash?.created_order_code ?? '').trim();
        const createdModeRaw = String(flash?.created_mode ?? base.createdMode).toUpperCase();
        const createdPreorderStatusRaw = String(flash?.created_preorder_status ?? base.createdPreorderStatus).toUpperCase();

        const createdOrderId = Number(createdOrderIdRaw);
        const createdOrderRef: string | number | null = createdOrderCodeRaw !== ''
            ? createdOrderCodeRaw
            : (Number.isFinite(createdOrderId) && createdOrderId > 0 ? createdOrderId : null);

        return {
            ...base,
            createdOrderRef,
            createdMode: ['RETAIL', 'PREORDER', 'RETURN'].includes(createdModeRaw)
                ? (createdModeRaw as 'RETAIL' | 'PREORDER' | 'RETURN')
                : base.createdMode,
            createdPreorderStatus: createdPreorderStatusRaw === 'DRAFT' ? 'DRAFT' : 'PREORDER',
        };
    };

    const resetTabAfterSuccess = () => {
        if (typeof onSuccessReset === 'function') {
            onSuccessReset();
            return;
        }

        const tab = activeTab.value;
        if (!tab) return;

        tab.customer_id = null;
        tab.seller_id = null;
        tab.warehouse_id = null;
        tab.order_type = 'RETAIL';
        tab.price_list_id = null;
        tab.items = [];
        tab.surcharge_id = null;
        tab.discount_amount = 0;
        tab.amount_received = null;
        tab.note = '';
        tab.search_query = '';

        if (mode.value === 'RETURN') {
            returnSourceOrderId.value = null;
        }
    };

    const mapCommonOrderPayload = () => {
        const tab = activeTab.value;
        if (!tab) return false;

        form.customer_id = tab.customer_id;
        form.seller_id = tab.seller_id;
        form.warehouse_id = tab.warehouse_id;
        form.order_date = tab.order_date;
        form.type = tab.order_type;
        form.discount = Number(tab.discount_amount || 0);
        form.note = tab.note;
        form.items = tab.items.map((item) => ({
            product_variant_id: item.product_variant_id,
            unit_name: item.unit_name,
            conversion_rate: Math.max(1, Math.round(Number(item.conversion_rate || 1))),
            quantity: item.quantity,
            price: item.price,
            discount: 0,
            tax_rate: 0,
            note: item.note || '',
        }));

        form.surcharges = selectedSurcharge.value
            ? [{
                name: selectedSurcharge.value.name,
                type: selectedSurcharge.value.type,
                value: Number(selectedSurcharge.value.value),
                amount: Number(surchargeAmount.value),
                is_refundable: Boolean(selectedSurcharge.value.is_refundable),
            }]
            : [];

        return true;
    };

    const validateBeforeSubmit = () => {
        const tab = activeTab.value;
        if (!tab) return false;

        if (selectedWarehouseId.value === null) {
            checkoutWarningMessage.value = 'Vui lòng chọn Kho trước khi thao tác.';
            return false;
        }

        if (selectedSellerId.value === null && mode.value !== 'RETURN') {
            checkoutWarningMessage.value = 'Vui lòng chọn Người bán trước khi thao tác.';
            return false;
        }

        if (!['RETAIL', 'WHOLESALE'].includes(selectedType.value) && mode.value !== 'RETURN') {
            checkoutWarningMessage.value = 'Vui lòng chọn Loại đơn (Buôn/Lẻ) hợp lệ.';
            return false;
        }

        if (tab.items.length === 0) {
            checkoutWarningMessage.value = 'Vui lòng chọn ít nhất 1 sản phẩm.';
            return false;
        }

        if (mode.value === 'RETURN' && returnSourceOrderId.value === null) {
            checkoutWarningMessage.value = 'Vui lòng chọn đơn hàng nguồn để tạo phiếu trả.';
            return false;
        }

        checkoutWarningMessage.value = '';
        return true;
    };

    const submitRetail = () => {
        if (!mapCommonOrderPayload()) return;

        if (isEditingOrder.value) {
            form.create_receivable = false;
            form.status = (String(editingOrderStatus?.value ?? 'DRAFT').toUpperCase() || 'DRAFT') as typeof form.status;
            form.paid_amount = Math.max(0, Number(amountReceived.value || 0));
            form.revision_reason = 'Cập nhật từ màn hình POS';
            form.replace_invoice = form.status === 'COMPLETED'
                && Boolean(useInvoiceOnSale?.value)
                && Boolean(editingOrderHasInvoice?.value);

            form.put(`${tenantRoutes.orders.update(String(editingOrderRef?.value ?? ''))}?from_sales_edit=1`, {
                preserveState: true,
                preserveScroll: true,
                onError: (errors) => {
                    checkoutWarningMessage.value = resolveCheckoutErrorMessage(errors as Record<string, string>);
                },
                onSuccess: () => {
                    resetTabAfterSuccess();
                },
            });

            return;
        }

        form.create_receivable = shouldCreateReceivable.value;
        form.status = selectedType.value === 'RETAIL'
            ? 'COMPLETED'
            : (form.create_receivable ? 'CONFIRMED' : 'COMPLETED');
        form.paid_amount = form.create_receivable ? 0 : Math.max(0, Number(amountReceived.value || 0));

        const successSnapshot = createSuccessSnapshot();

        form.post(tenantRoutes.sales.store(), {
            preserveState: true,
            preserveScroll: true,
            onError: (errors) => {
                checkoutWarningMessage.value = resolveCheckoutErrorMessage(errors as Record<string, string>);
            },
            onSuccess: (page) => {
                successModalData.value = resolveSuccessSnapshot(successSnapshot, page);
                successModalOpen.value = true;
                resetTabAfterSuccess();
            },
        });
    };

    const submitPreorder = () => {
        if (!mapCommonOrderPayload()) return;

        form.create_receivable = false;
        form.status = preorderStatus.value;
        form.paid_amount = Math.max(0, Number(amountReceived.value || 0));

        const successSnapshot = createSuccessSnapshot();

        form.post(tenantRoutes.sales.preorder(), {
            preserveState: true,
            preserveScroll: true,
            onError: (errors) => {
                checkoutWarningMessage.value = resolveCheckoutErrorMessage(errors as Record<string, string>);
            },
            onSuccess: (page) => {
                successModalData.value = resolveSuccessSnapshot(successSnapshot, page);
                successModalOpen.value = true;
                resetTabAfterSuccess();
            },
        });
    };

    const submitReturn = () => {
        const tab = activeTab.value;
        if (!tab || returnSourceOrderId.value === null) return;

        const selectedSourceOrder = returnSourceOptions.value.find((item) => Number(item.id) === Number(returnSourceOrderId.value));
        if (!selectedSourceOrder || !isReturnableOrder(selectedSourceOrder.status)) {
            checkoutWarningMessage.value = 'Chỉ được tạo trả hàng từ đơn đã hoàn thành.';
            return;
        }

        const returnItems = tab.items
            .map((item) => {
                const quantity = Math.max(0, Math.round(Number(item.quantity || 0)));
                if (quantity <= 0) return null;

                return {
                    order_item_id: item.order_item_id,
                    product_variant_id: item.product_variant_id,
                    quantity,
                    price: Number(item.price || 0),
                    refund_amount: Number(item.price || 0) * quantity,
                    condition: 'GOOD',
                };
            })
            .filter((item): item is NonNullable<typeof item> => item !== null);

        if (returnItems.length === 0) {
            checkoutWarningMessage.value = 'Vui lòng chọn ít nhất 1 dòng trả hàng có số lượng > 0.';
            return;
        }

        const payload = {
            order_id: returnSourceOrderId.value,
            customer_id: tab.customer_id,
            warehouse_id: tab.warehouse_id,
            type: 'PARTIAL',
            refund_method: 'CASH',
            reason: tab.note || 'Trả hàng từ POS',
            note: tab.note || null,
            items: returnItems,
        };

        const successSnapshot = createSuccessSnapshot();

        router.post(tenantRoutes.sales.return(), payload, {
            preserveState: true,
            preserveScroll: true,
            onError: (errors) => {
                checkoutWarningMessage.value = resolveCheckoutErrorMessage(errors as Record<string, string>);
            },
            onSuccess: (page) => {
                successModalData.value = resolveSuccessSnapshot(successSnapshot, page);
                successModalOpen.value = true;
                resetTabAfterSuccess();
            },
        });
    };

    const submitPos = () => {
        if (isEditingOrder.value && mode.value !== 'RETAIL') {
            checkoutWarningMessage.value = 'Chế độ sửa đơn chỉ hỗ trợ luồng bán hàng thường (RETAIL).';
            return;
        }

        if (!validateBeforeSubmit()) return;

        if (mode.value === 'RETURN') {
            submitReturn();
            return;
        }

        if (mode.value === 'PREORDER') {
            submitPreorder();
            return;
        }

        submitRetail();
    };

    return {
        returnSourceOptions,
        submitPos,
    };
};
