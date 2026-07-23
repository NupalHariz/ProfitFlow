import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import ProductCategoryController from '@/actions/App/Http/Controllers/ProductCategories/ProductCategoryController';
import ProductCategoryForm from '@/components/product-categories/product-category-form';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

export default function CreateProductCategory() {
    return (
        <>
            <Head title="Create product category" />

            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <Button asChild variant="ghost" className="mb-2 -ml-3">
                        <Link href={ProductCategoryController.index()}>
                            <ArrowLeft />
                            Product categories
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Create product category
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Add a category used to organize product master data and
                        match category-specific profit rules.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Category details</CardTitle>
                        <CardDescription>
                            The system owns the stable category slug and
                            resolves naming collisions automatically.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ProductCategoryForm mode="create" />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CreateProductCategory.layout = {
    breadcrumbs: [
        {
            title: 'Product categories',
            href: ProductCategoryController.index(),
        },
        {
            title: 'Create',
            href: ProductCategoryController.create(),
        },
    ],
};
