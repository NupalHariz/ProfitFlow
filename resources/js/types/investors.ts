export type InvestorLinkedRecipient = {
    id: number;
    code: string;
    type: 'investor' | 'company';
    display_name: string;
    is_active: boolean;
};

export type InvestorFormData = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    is_active: boolean;
    created_at: string | null;
    updated_at: string | null;
};

export type InvestorSummary = InvestorFormData & {
    linked_recipient: InvestorLinkedRecipient | null;
};

export type InvestorFilters = {
    search: string;
    status: 'all' | 'active' | 'inactive';
};

export type InvestorPagination = {
    data: InvestorSummary[];
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

export type InvestorAllocationSummary = {
    allocation_count: number;
    distribution_count: number;
};
