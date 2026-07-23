export type TransactionStatus =
    | 'draft'
    | 'pending_distribution'
    | 'processing_distribution'
    | 'distributed'
    | 'distribution_failed'
    | 'cancelled';

export type TransactionCustomerLookup = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
};

export type TransactionProductLookup = {
    id: number;
    name: string;
    sku: string;
    category: {
        id: number;
        name: string;
    };
    default_selling_price: string;
    default_production_cost: string;
};

export type TransactionItemSnapshot = {
    product_name_snapshot: string;
    product_sku_snapshot: string;
    category_name_snapshot: string | null;
    quantity: number;
    unit_selling_price: string;
    unit_production_cost: string;
    total_revenue: string;
    total_production_cost: string;
    net_profit: string;
};

export type TransactionFormItem = {
    id?: number;
    product_id: number | null;
    quantity: number;
    unit_selling_price: string;
    current_product_available?: boolean;
    snapshot?: TransactionItemSnapshot;
};

export type TransactionFormData = {
    customer_id: number | null;
    transaction_date: string;
    current_customer_available?: boolean;
    items: TransactionFormItem[];
};

export type TransactionDetail = {
    id: number;
    transaction_number: string;
    transaction_date: string;
    status: TransactionStatus;
    can_edit: boolean;
    total_revenue: string;
    total_production_cost: string;
    total_net_profit: string;
    created_at: string;
    completed_at: string | null;
    distribution_started_at: string | null;
    distributed_at: string | null;
    cancelled_at: string | null;
    failure_reason: string | null;
    customer: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
        address: string | null;
        is_deleted: boolean;
    };
    creator: {
        id: number;
        name: string;
        email: string;
    };
    items: (TransactionItemSnapshot & {
        id: number;
        product_id: number | null;
        product_category_id: number | null;
        applied_profit_scheme_version_id: number | null;
        rule_resolved_at: string | null;
    })[];
};
