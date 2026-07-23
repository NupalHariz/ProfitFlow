import type { TransactionStatus } from './transactions';

export type CustomerSummary = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    transactions_count: number;
    created_at: string | null;
    updated_at: string | null;
};

export type CustomerFormData = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    created_at: string | null;
    updated_at: string | null;
};

export type CustomerFilters = {
    search: string;
};

export type CustomerPagination = {
    data: CustomerSummary[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    links: {
        previous: string | null;
        next: string | null;
        pages: {
            page: number;
            url: string;
            active: boolean;
        }[];
    };
};

export type CustomerTransactionSummary = {
    total_count: number;
    draft_count: number;
    distributed_count: number;
};

export type CustomerRecentTransaction = {
    id: number;
    transaction_number: string;
    transaction_date: string;
    status: TransactionStatus;
    total_revenue: string;
    total_net_profit: string;
};
