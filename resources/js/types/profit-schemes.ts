export type ProfitSchemeSummary = {
    id: number;
    name: string;
    code: string;
    is_active: boolean;
};

export type ProfitSchemeCategoryLookup = {
    id: number;
    name: string;
    is_active: boolean;
    is_deleted: boolean;
};

export type ProfitRecipientLookup = {
    id: number;
    code: string;
    display_name: string;
    type: string;
    is_active: boolean;
    is_available: boolean;
    investor: {
        id: number;
        name: string;
        is_active: boolean;
    } | null;
};

export type ProfitSchemeAllocation = {
    id: number;
    profit_recipient_id: number;
    percentage: string;
};

export type ProfitSchemeVersionFormData = {
    id: number;
    version: number;
    product_category_id: number | null;
    minimum_profit: string | null;
    maximum_profit: string | null;
    effective_from: string;
    effective_until: string | null;
    priority: number;
    is_active: boolean;
    published_at: string | null;
    is_published: boolean;
    is_historically_referenced: boolean;
    is_immutable: boolean;
    can_edit: boolean;
    allocation_total: string;
    allocations: ProfitSchemeAllocation[];
};
