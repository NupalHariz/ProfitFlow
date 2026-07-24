import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, TriangleAlert } from 'lucide-react';
import ProductController from '@/actions/App/Http/Controllers/Products/ProductController';
import DeleteProductDialog from '@/components/products/delete-product-dialog';
import ProductAvailabilityBadge from '@/components/products/product-availability-badge';
import ProductForm from '@/components/products/product-form';
import ProductStatusBadge from '@/components/products/product-status-badge';
import ProductStatusDialog from '@/components/products/product-status-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { ProductCategoryOption, ProductFormData } from '@/types';

type PageProps = {
    product: ProductFormData;
    categories: ProductCategoryOption[];
    category_option_limit: number;
};

export default function EditProduct({
    product,
    categories,
    category_option_limit: categoryOptionLimit,
}: PageProps) {
    return (
        <>
            <Head title={`Edit ${product.name}`} />

            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <Button asChild variant="ghost" className="mb-2 -ml-3">
                        <Link href={ProductController.index()}>
                            <ArrowLeft />
                            Products
                        </Link>
                    </Button>
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Edit {product.name}
                        </h1>
                        <ProductStatusBadge active={product.is_active} />
                        {!product.category.is_active && (
                            <Badge variant="outline">Category inactive</Badge>
                        )}
                    </div>
                    <p className="mt-1 font-mono text-sm text-muted-foreground">
                        {product.sku}
                    </p>
                </div>

                {!product.category.is_active && (
                    <Alert>
                        <TriangleAlert />
                        <AlertTitle>
                            Unavailable for transaction drafts
                        </AlertTitle>
                        <AlertDescription>
                            Its own status is preserved, but an inactive
                            category excludes it from new or updated transaction
                            drafts. Other edits may keep this category
                            unchanged.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_21rem]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Product details</CardTitle>
                            <CardDescription>
                                Update product information and default pricing.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ProductForm
                                mode="edit"
                                product={product}
                                categories={categories}
                                categoryOptionLimit={categoryOptionLimit}
                            />
                        </CardContent>
                    </Card>

                    <div className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Current category</CardTitle>
                                <CardDescription>
                                    The product is available only when both
                                    statuses are active.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between gap-3">
                                    <span className="font-medium">
                                        {product.category.name}
                                    </span>
                                    <Badge
                                        variant={
                                            product.category.is_active
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {product.category.is_active
                                            ? 'Active'
                                            : 'Inactive'}
                                    </Badge>
                                </div>
                                <div className="flex items-center justify-between gap-3 border-t pt-4">
                                    <span className="text-sm text-muted-foreground">
                                        Transaction availability
                                    </span>
                                    <ProductAvailabilityBadge
                                        productActive={product.is_active}
                                        categoryActive={
                                            product.category.is_active
                                        }
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Status</CardTitle>
                                <CardDescription>
                                    Manage whether this product is available.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                <ProductStatusDialog
                                    productId={product.id}
                                    productName={product.name}
                                    isActive={product.is_active}
                                    categoryIsActive={
                                        product.category.is_active
                                    }
                                />
                                <DeleteProductDialog
                                    productId={product.id}
                                    productName={product.name}
                                />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

EditProduct.layout = {
    breadcrumbs: [
        {
            title: 'Products',
            href: ProductController.index(),
        },
        {
            title: 'Edit',
            href: '#',
        },
    ],
};
