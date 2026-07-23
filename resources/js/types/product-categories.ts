export type ProductCategorySummary = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    products_count: number;
    created_at: string | null;
    updated_at: string | null;
};

export type ProductCategoryFormData = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
};

export type ProductCategoryFilters = {
    search: string;
    status: 'all' | 'active' | 'inactive';
};

export type ProductCategoryPagination = {
    data: ProductCategorySummary[];
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
