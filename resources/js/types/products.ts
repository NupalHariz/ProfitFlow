export type ProductCategoryOption = {
    id: number;
    name: string;
    is_active: boolean;
    is_current?: boolean;
};

export type ProductCategoryReference = {
    id: number;
    name: string;
    is_active: boolean;
};

export type ProductSummary = {
    id: number;
    name: string;
    sku: string;
    description: string | null;
    default_selling_price: string;
    default_production_cost: string;
    is_active: boolean;
    updated_at: string | null;
    category: ProductCategoryReference;
};

export type ProductFormData = {
    id: number;
    product_category_id: number;
    name: string;
    sku: string;
    description: string | null;
    default_selling_price: string;
    default_production_cost: string;
    is_active: boolean;
    category: ProductCategoryReference;
};

export type ProductFilters = {
    search: string;
    category: number | null;
    status: 'all' | 'active' | 'inactive';
};

export type ProductPagination = {
    data: ProductSummary[];
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
