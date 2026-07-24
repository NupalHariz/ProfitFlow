import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import ProductController from '@/actions/App/Http/Controllers/Products/ProductController';
import ProductForm from '@/components/products/product-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ProductCategoryOption } from '@/types';

type PageProps = {
    categories: ProductCategoryOption[];
    category_option_limit: number;
};

export default function CreateProduct({
    categories,
    category_option_limit: categoryOptionLimit,
}: PageProps) {
    return (
        <>
            <Head title="Create product" />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <Button asChild variant="ghost" className="mb-2 -ml-3">
                        <Link href={ProductController.index()}>
                            <ArrowLeft />
                            Products
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Create product
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Add a product for use in transactions.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Product details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ProductForm
                            mode="create"
                            categories={categories}
                            categoryOptionLimit={categoryOptionLimit}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CreateProduct.layout = {
    breadcrumbs: [
        {
            title: 'Products',
            href: ProductController.index(),
        },
        {
            title: 'Create',
            href: ProductController.create(),
        },
    ],
};
