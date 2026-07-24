export type ProfitRecipientType = 'investor' | 'company';

export type ProfitRecipientInvestor = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    is_active: boolean;
};

export type ProfitRecipientData = {
    id: number;
    code: string;
    type: ProfitRecipientType;
    display_name: string;
    is_active: boolean;
    created_at: string | null;
    updated_at: string | null;
};

export type ProfitRecipientUsageSummary = {
    allocation_count: number;
    draft_allocation_count?: number;
    published_allocation_count?: number;
    distribution_count: number;
};

export type ProfitRecipientSummary = ProfitRecipientData & {
    investor: ProfitRecipientInvestor | null;
    usage_summary: ProfitRecipientUsageSummary;
};

export type ProfitRecipientFilters = {
    search: string;
    type: 'all' | ProfitRecipientType;
    status: 'all' | 'active' | 'inactive';
};

export type ProfitRecipientPagination = {
    data: ProfitRecipientSummary[];
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

export type ProfitRecipientTypeOption = {
    value: ProfitRecipientType;
    label: string;
};

export type ProfitRecipientCreateDefaults = {
    code: string;
    type: ProfitRecipientType;
    investor_id: number | null;
    display_name: string;
    is_active: boolean;
};
