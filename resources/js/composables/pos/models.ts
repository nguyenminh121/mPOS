export type WarehouseOption = { id: number; name: string };
export type CategoryOption = { id: number; code?: string | null; name: string; description?: string | null; parent_id?: number | null; is_active: boolean };
export type UomOption = { id: number; name: string; symbol?: string | null };
export type CustomerOption = { id: number; code?: string | null; name: string; phone?: string | null; price_list_id?: number | null };
export type SellerOption = { id: number; name: string };
export type PriceListItemOption = { product_variant_id: number; unit_name: string; price: number; min_quantity: number };
export type PriceListOption = { id: number; code?: string | null; name: string; is_default: boolean; priority: number; items: PriceListItemOption[] };

export type VariantUnitOption = {
    name: string;
    conversion_rate: number;
    retail_price?: number | null;
};

export type VariantStockOption = {
    warehouse_id: number;
    warehouse_name?: string | null;
    quantity: number;
};

export type VariantOption = {
    id: number;
    product_id?: number | null;
    product_name?: string | null;
    name: string;
    sku?: string | null;
    barcode?: string | null;
    base_unit?: string | null;
    price: number;
    unit_options?: VariantUnitOption[];
    total_stock?: number;
    stock_by_warehouse?: VariantStockOption[];
    image_path?: string | null;
    preorder_quantity?: number | null;
};

export type SurchargeOption = { id: number; name: string; type: 'PERCENT' | 'FIXED_AMOUNT'; value: number; is_refundable: boolean };

export type SaleItemOption = {
	id?: number;
    product_variant_id?: number;
    unit_name?: string;
    conversion_rate?: number;
    quantity?: number;
    returned_quantity?: number;
    price?: number;
};

export type SaleOption = {
    id?: number;
    customer_id?: number | null;
    code?: string;
    type?: 'RETAIL' | 'WHOLESALE' | 'ONLINE' | 'RETURN';
    status?: string;
    order_date?: string;
    created_at?: string;
    final_amount?: number;
    surcharge?: number;
    items?: SaleItemOption[];
};

export type CartItem = {
    order_item_id?: number;
    product_variant_id: number;
    name: string;
    sku?: string | null;
    unit_name: string;
    conversion_rate: number;
    unit_options: VariantUnitOption[];
    price: number;
    quantity: number;
    sold_quantity?: number;
    returned_quantity?: number;
    note: string;
};

export type PosDraftTab = {
    id: string;
    label: string;
    search_query: string;
    customer_id: number | null;
    seller_id: number | null;
    warehouse_id: number | null;
    order_type: 'RETAIL' | 'WHOLESALE';
    price_list_id: number | null;
    order_date: string;
    surcharge_id: number | null;
    discount_amount: number;
    amount_received: number | null;
    note: string;
    items: CartItem[];
};

export type AddProductPayload = {
    barcode: string;
    sku: string;
    name: string;
    variant_name: string | null;
    base_unit: string;
    cost_price: number;
    retail_price: number;
    wholesale_price: number;
    initial_stock: number;
    warehouse_id: number | null;
    category_id: number | null;
    image_file: File | null;
};

export type StockWarningOption = {
    product_variant_id: number;
    display_name: string;
    required_base_stock: number;
    available_base_stock: number;
};
