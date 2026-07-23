import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import ProductController from '@/actions/App/Http/Controllers/Products/ProductController';
import DeleteProductDialog from '@/components/products/delete-product-dialog';
import ProductStatusBadge from '@/components/products/product-status-badge';
import ProductStatusDialog from '@/components/products/product-status-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatRupiah } from '@/lib/money';
import type {
    ProductCategoryOption,
    ProductFilters,
    ProductPagination,
} from '@/types';

type PageProps = {
    filters: ProductFilters;
    category_options: ProductCategoryOption[];
    products: ProductPagination;
};

export default function ProductIndex({
    filters,
    category_options: categoryOptions,
    products,
}: PageProps) {
    const [status, setStatus] = useState(filters.status);
    const [category, setCategory] = useState(
        filters.category === null ? 'all' : String(filters.category),
    );

    return (
        <>
            <Head title="Products" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Products
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Maintain product identities, category assignments,
                            transaction defaults, and availability.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={ProductController.create()}>
                            <Plus />
                            Create product
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardContent>
                        <Form
                            {...ProductController.index.form()}
                            className="grid gap-3 lg:grid-cols-[minmax(0,1fr)_15rem_12rem_auto_auto]"
                        >
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                <Input
                                    name="search"
                                    defaultValue={filters.search}
                                    placeholder="Search name or SKU"
                                    className="pl-9"
                                />
                            </div>
                            <input
                                type="hidden"
                                name="category"
                                value={category === 'all' ? '' : category}
                            />
                            <Select
                                value={category}
                                onValueChange={setCategory}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All categories
                                    </SelectItem>
                                    {categoryOptions.map((option) => (
                                        <SelectItem
                                            key={option.id}
                                            value={String(option.id)}
                                        >
                                            {option.name}
                                            {!option.is_active
                                                ? ' (Inactive)'
                                                : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <input type="hidden" name="status" value={status} />
                            <Select
                                value={status}
                                onValueChange={(value) =>
                                    setStatus(value as ProductFilters['status'])
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All statuses
                                    </SelectItem>
                                    <SelectItem value="active">
                                        Active
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        Inactive
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Button>Apply filters</Button>
                            {(filters.search !== '' ||
                                filters.category !== null ||
                                filters.status !== 'all') && (
                                <Button variant="ghost" asChild>
                                    <Link href={ProductController.index()}>
                                        Clear
                                    </Link>
                                </Button>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {products.data.length === 0 ? (
                    <Card className="border-dashed">
                        <CardHeader className="items-center py-12 text-center">
                            <CardTitle>No products found</CardTitle>
                            <CardDescription>
                                Adjust the filters or create the first product.
                            </CardDescription>
                            <Button asChild className="mt-3">
                                <Link href={ProductController.create()}>
                                    <Plus />
                                    Create product
                                </Link>
                            </Button>
                        </CardHeader>
                    </Card>
                ) : (
                    <>
                        <div className="grid gap-3 lg:hidden">
                            {products.data.map((product) => (
                                <Card key={product.id}>
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <CardTitle>
                                                    {product.name}
                                                </CardTitle>
                                                <CardDescription className="mt-1 font-mono">
                                                    {product.sku}
                                                </CardDescription>
                                            </div>
                                            <ProductStatusBadge
                                                active={product.is_active}
                                            />
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex flex-wrap items-center gap-2 text-sm">
                                            <span>{product.category.name}</span>
                                            {!product.category.is_active && (
                                                <Badge variant="outline">
                                                    Category inactive
                                                </Badge>
                                            )}
                                        </div>
                                        <div className="grid grid-cols-2 gap-3 text-sm">
                                            <div>
                                                <p className="text-xs text-muted-foreground">
                                                    Selling price
                                                </p>
                                                <p className="font-medium">
                                                    {formatRupiah(
                                                        product.default_selling_price,
                                                    )}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs text-muted-foreground">
                                                    Production cost
                                                </p>
                                                <p className="font-medium">
                                                    {formatRupiah(
                                                        product.default_production_cost,
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {product.description ??
                                                'No description provided.'}
                                        </p>
                                        <div className="flex flex-wrap gap-2">
                                            <ProductActions
                                                id={product.id}
                                                name={product.name}
                                                isActive={product.is_active}
                                                categoryIsActive={
                                                    product.category.is_active
                                                }
                                            />
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>

                        <Card className="hidden overflow-hidden py-0 lg:block">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b bg-muted/40 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-5 py-3">
                                                Product
                                            </th>
                                            <th className="px-5 py-3">
                                                Category
                                            </th>
                                            <th className="px-5 py-3">
                                                Selling price
                                            </th>
                                            <th className="px-5 py-3">
                                                Production cost
                                            </th>
                                            <th className="px-5 py-3">
                                                Status
                                            </th>
                                            <th className="px-5 py-3">
                                                Updated
                                            </th>
                                            <th className="px-5 py-3">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {products.data.map((product) => (
                                            <tr
                                                key={product.id}
                                                className="hover:bg-muted/30"
                                            >
                                                <td className="max-w-sm px-5 py-4">
                                                    <p className="font-medium">
                                                        {product.name}
                                                    </p>
                                                    <p className="font-mono text-xs text-muted-foreground">
                                                        {product.sku}
                                                    </p>
                                                    {product.description && (
                                                        <p className="mt-1 line-clamp-1 text-xs text-muted-foreground">
                                                            {
                                                                product.description
                                                            }
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span>
                                                            {
                                                                product.category
                                                                    .name
                                                            }
                                                        </span>
                                                        {!product.category
                                                            .is_active && (
                                                            <Badge variant="outline">
                                                                Inactive
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-5 py-4 whitespace-nowrap">
                                                    {formatRupiah(
                                                        product.default_selling_price,
                                                    )}
                                                </td>
                                                <td className="px-5 py-4 whitespace-nowrap">
                                                    {formatRupiah(
                                                        product.default_production_cost,
                                                    )}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <ProductStatusBadge
                                                        active={
                                                            product.is_active
                                                        }
                                                    />
                                                </td>
                                                <td className="px-5 py-4 whitespace-nowrap text-muted-foreground">
                                                    {formatDate(
                                                        product.updated_at,
                                                    )}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <div className="flex flex-wrap gap-2">
                                                        <ProductActions
                                                            id={product.id}
                                                            name={product.name}
                                                            isActive={
                                                                product.is_active
                                                            }
                                                            categoryIsActive={
                                                                product.category
                                                                    .is_active
                                                            }
                                                        />
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Card>
                    </>
                )}

                {products.meta.last_page > 1 && (
                    <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
                        <p className="text-sm text-muted-foreground">
                            Showing {products.meta.from}–{products.meta.to} of{' '}
                            {products.meta.total}
                        </p>
                        <div className="flex items-center gap-1">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={products.links.previous === null}
                                asChild={products.links.previous !== null}
                            >
                                {products.links.previous ? (
                                    <Link href={products.links.previous}>
                                        Previous
                                    </Link>
                                ) : (
                                    <span>Previous</span>
                                )}
                            </Button>
                            {products.links.pages.map((link) => (
                                <Button
                                    key={link.page}
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    asChild
                                >
                                    <Link href={link.url}>{link.page}</Link>
                                </Button>
                            ))}
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={products.links.next === null}
                                asChild={products.links.next !== null}
                            >
                                {products.links.next ? (
                                    <Link href={products.links.next}>Next</Link>
                                ) : (
                                    <span>Next</span>
                                )}
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

function ProductActions({
    id,
    name,
    isActive,
    categoryIsActive,
}: {
    id: number;
    name: string;
    isActive: boolean;
    categoryIsActive: boolean;
}) {
    return (
        <>
            <Button asChild variant="outline" size="sm">
                <Link href={ProductController.edit(id)}>
                    <Pencil />
                    Edit
                </Link>
            </Button>
            <ProductStatusDialog
                productId={id}
                productName={name}
                isActive={isActive}
                categoryIsActive={categoryIsActive}
            />
            <DeleteProductDialog productId={id} productName={name} />
        </>
    );
}

function formatDate(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

ProductIndex.layout = {
    breadcrumbs: [
        {
            title: 'Products',
            href: ProductController.index(),
        },
    ],
};
