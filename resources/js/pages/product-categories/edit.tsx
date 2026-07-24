import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import ProductCategoryController from '@/actions/App/Http/Controllers/ProductCategories/ProductCategoryController';
import DeleteProductCategoryDialog from '@/components/product-categories/delete-product-category-dialog';
import ProductCategoryForm from '@/components/product-categories/product-category-form';
import ProductCategoryStatusBadge from '@/components/product-categories/product-category-status-badge';
import ProductCategoryStatusDialog from '@/components/product-categories/product-category-status-dialog';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { ProductCategoryFormData } from '@/types';

export default function EditProductCategory({
    category,
}: {
    category: ProductCategoryFormData;
}) {
    return (
        <>
            <Head title={`Edit ${category.name}`} />

            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <Button asChild variant="ghost" className="mb-2 -ml-3">
                        <Link href={ProductCategoryController.index()}>
                            <ArrowLeft />
                            Product categories
                        </Link>
                    </Button>
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Edit {category.name}
                        </h1>
                        <ProductCategoryStatusBadge
                            active={category.is_active}
                        />
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Update category details.
                    </p>
                </div>

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Category details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ProductCategoryForm
                                mode="edit"
                                category={category}
                            />
                        </CardContent>
                    </Card>

                    <div className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Status</CardTitle>
                                <CardDescription>
                                    Manage whether this category is available.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                <ProductCategoryStatusDialog
                                    categoryId={category.id}
                                    categoryName={category.name}
                                    isActive={category.is_active}
                                />
                                <DeleteProductCategoryDialog
                                    categoryId={category.id}
                                    categoryName={category.name}
                                />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

EditProductCategory.layout = {
    breadcrumbs: [
        {
            title: 'Product categories',
            href: ProductCategoryController.index(),
        },
        {
            title: 'Edit',
            href: '#',
        },
    ],
};
