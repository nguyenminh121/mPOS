import type { ComputedRef, Ref } from 'vue';
import type { PosDraftTab, SurchargeOption } from '@/composables/pos/models';
import { tenantRoutes } from '@/lib/tenantRoutes';

type PosSuccessModalData = {
    totalAmount: number;
    amountReceived: number;
    changeAmount: number;
    itemCount: number;
    customerName: string;
};

type PosSaleForm = {
    customer_id: number | null;
    seller_id: number | null;
    warehouse_id: number | null;
    status: 'CONFIRMED' | 'DRAFT' | 'COMPLETED';
    create_receivable: boolean;
    paid_amount: number;
    order_date: string;
    type: 'RETAIL' | 'WHOLESALE';
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
            onSuccess: () => void;
        }
    ) => void;
};

type UsePosCheckoutParams = {
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
};

export const usePosCheckout = ({
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
}: UsePosCheckoutParams) => {
    const resolveCheckoutErrorMessage = (errors: Record<string, string>) => {
        const priorityKeys = [
            'items',
            'warehouse_id',
            'customer_id',
            'seller_id',
            'order_date',
            'paid_amount',
        ];

        for (const key of priorityKeys) {
            const message = errors[key];
            if (typeof message === 'string' && message.trim() !== '') {
                return message;
            }
        }

        const firstKey = Object.keys(errors)[0];
        if (!firstKey) {
            return 'Không thể tạo đơn hàng. Vui lòng kiểm tra dữ liệu và thử lại.';
        }

        return errors[firstKey] || 'Không thể tạo đơn hàng. Vui lòng kiểm tra dữ liệu và thử lại.';
    };

    const submitOrder = () => {
        const tab = activeTab.value;
        if (!tab) return;

        if (selectedWarehouseId.value === null) {
            checkoutWarningMessage.value = 'Vui lòng chọn Kho trước khi thanh toán.';
            return;
        }

        if (selectedSellerId.value === null) {
            checkoutWarningMessage.value = 'Vui lòng chọn Người bán trước khi thanh toán.';
            return;
        }

        if (!['RETAIL', 'WHOLESALE'].includes(selectedType.value)) {
            checkoutWarningMessage.value = 'Vui lòng chọn Loại đơn (Buôn/Lẻ) hợp lệ.';
            return;
        }

        if (tab.items.length === 0) {
            checkoutWarningMessage.value = 'Vui lòng chọn ít nhất 1 sản phẩm để tạo đơn hàng.';
            return;
        }

        checkoutWarningMessage.value = '';

        form.customer_id = tab.customer_id;
        form.seller_id = tab.seller_id;
        form.warehouse_id = tab.warehouse_id;
        form.create_receivable = shouldCreateReceivable.value;
        form.status = form.create_receivable ? 'CONFIRMED' : 'COMPLETED';
        form.paid_amount = form.create_receivable ? 0 : Math.max(0, Number(amountReceived.value || 0));
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

        const successSnapshot = {
            totalAmount: Number(grandTotal.value || 0),
            amountReceived: form.create_receivable ? 0 : Number(amountReceived.value || 0),
            changeAmount: form.create_receivable
                ? 0
                : Math.max(0, Number(amountReceived.value || 0) - Number(grandTotal.value || 0)),
            itemCount: tab.items.length,
            customerName: selectedCustomerName.value || 'Khách lẻ',
        };

        form.post(tenantRoutes.sales.store(), {
            preserveState: true,
            preserveScroll: true,
            onError: (errors) => {
                checkoutWarningMessage.value = resolveCheckoutErrorMessage(errors as Record<string, string>);
            },
            onSuccess: () => {
                successModalData.value = successSnapshot;
                successModalOpen.value = true;

                tab.items = [];
                tab.surcharge_id = null;
                tab.discount_amount = 0;
                tab.amount_received = null;
                tab.note = '';
                tab.search_query = '';
            },
        });
    };

    return {
        submitOrder,
    };
};
