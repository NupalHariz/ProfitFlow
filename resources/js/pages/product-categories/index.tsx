import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import ProductCategoryController from '@/actions/App/Http/Controllers/ProductCategories/ProductCategoryController';
import DeleteProductCategoryDialog from '@/components/product-categories/delete-product-category-dialog';
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
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    ProductCategoryFilters,
    ProductCategoryPagination,
} from '@/types';

type PageProps = {
    filters: ProductCategoryFilters;
    categories: ProductCategoryPagination;
};

export default function ProductCategoryIndex({
    filters,
    categories,
}: PageProps) {
    const [status, setStatus] = useState(filters.status);

    return (
        <>
            <Head title="Product categories" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Product categories
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Organize products and manage category availability.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={ProductCategoryController.create()}>
                            <Plus />
                            Create category
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardContent>
                        <Form
                            {...ProductCategoryController.index.form()}
                            className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem_auto_auto]"
                        >
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                <Input
                                    name="search"
                                    defaultValue={filters.search}
                                    placeholder="Search name or description"
                                    aria-label="Search product categories"
                                    className="pl-9"
                                />
                            </div>
                            <input type="hidden" name="status" value={status} />
                            <Select
                                value={status}
                                onValueChange={(value) =>
                                    setStatus(
                                        value as ProductCategoryFilters['status'],
                                    )
                                }
                            >
                                <SelectTrigger
                                    className="w-full"
                                    aria-label="Filter product categories by status"
                                >
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
                                filters.status !== 'all') && (
                                <Button variant="ghost" asChild>
                                    <Link
                                        href={ProductCategoryController.index()}
                                    >
                                        Clear
                                    </Link>
                                </Button>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {categories.data.length === 0 ? (
                    <Card className="border-dashed">
                        <CardHeader className="items-center py-12 text-center">
                            <CardTitle>No product categories found</CardTitle>
                            <CardDescription>
                                Adjust the filters or create the first category.
                            </CardDescription>
                            <Button asChild className="mt-3">
                                <Link href={ProductCategoryController.create()}>
                                    <Plus />
                                    Create category
                                </Link>
                            </Button>
                        </CardHeader>
                    </Card>
                ) : (
                    <>
                        <div className="grid gap-3 md:hidden">
                            {categories.data.map((category) => (
                                <Card key={category.id}>
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <CardTitle>
                                                    {category.name}
                                                </CardTitle>
                                            </div>
                                            <ProductCategoryStatusBadge
                                                active={category.is_active}
                                            />
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <p className="text-sm text-muted-foreground">
                                            {category.description ??
                                                'No description provided.'}
                                        </p>
                                        <div className="flex items-center justify-between text-sm">
                                            <span>
                                                {category.products_count}{' '}
                                                {category.products_count === 1
                                                    ? 'product'
                                                    : 'products'}
                                            </span>
                                            <span className="text-muted-foreground">
                                                Updated{' '}
                                                {formatDate(
                                                    category.updated_at,
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link
                                                    href={ProductCategoryController.edit(
                                                        category.id,
                                                    )}
                                                >
                                                    <Pencil />
                                                    Edit
                                                </Link>
                                            </Button>
                                            <ProductCategoryStatusDialog
                                                categoryId={category.id}
                                                categoryName={category.name}
                                                isActive={category.is_active}
                                            />
                                            <DeleteProductCategoryDialog
                                                categoryId={category.id}
                                                categoryName={category.name}
                                            />
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>

                        <Card className="hidden overflow-hidden py-0 md:block">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b bg-muted/40 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-5 py-3">Name</th>
                                            <th className="px-5 py-3">
                                                Description
                                            </th>
                                            <th className="px-5 py-3">
                                                Status
                                            </th>
                                            <th className="px-5 py-3">
                                                Products
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
                                        {categories.data.map((category) => (
                                            <tr
                                                key={category.id}
                                                className="hover:bg-muted/30"
                                            >
                                                <td className="px-5 py-4">
                                                    <p className="font-medium">
                                                        {category.name}
                                                    </p>
                                                </td>
                                                <td className="max-w-sm px-5 py-4 text-muted-foreground">
                                                    <p className="line-clamp-2">
                                                        {category.description ??
                                                            '—'}
                                                    </p>
                                                </td>
                                                <td className="px-5 py-4">
                                                    <ProductCategoryStatusBadge
                                                        active={
                                                            category.is_active
                                                        }
                                                    />
                                                </td>
                                                <td className="px-5 py-4">
                                                    {category.products_count}
                                                </td>
                                                <td className="px-5 py-4 whitespace-nowrap text-muted-foreground">
                                                    {formatDate(
                                                        category.updated_at,
                                                    )}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <div className="flex flex-wrap gap-2">
                                                        <Button
                                                            asChild
                                                            variant="outline"
                                                            size="sm"
                                                        >
                                                            <Link
                                                                href={ProductCategoryController.edit(
                                                                    category.id,
                                                                )}
                                                            >
                                                                <Pencil />
                                                                Edit
                                                            </Link>
                                                        </Button>
                                                        <ProductCategoryStatusDialog
                                                            categoryId={
                                                                category.id
                                                            }
                                                            categoryName={
                                                                category.name
                                                            }
                                                            isActive={
                                                                category.is_active
                                                            }
                                                        />
                                                        <DeleteProductCategoryDialog
                                                            categoryId={
                                                                category.id
                                                            }
                                                            categoryName={
                                                                category.name
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

                {categories.meta.last_page > 1 && (
                    <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
                        <p className="text-sm text-muted-foreground">
                            Showing {categories.meta.from}–{categories.meta.to}{' '}
                            of {categories.meta.total}
                        </p>
                        <div className="flex items-center gap-1">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={categories.links.previous === null}
                                asChild={categories.links.previous !== null}
                            >
                                {categories.links.previous ? (
                                    <Link href={categories.links.previous}>
                                        Previous
                                    </Link>
                                ) : (
                                    <span>Previous</span>
                                )}
                            </Button>
                            {categories.links.pages.map((link) => (
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
                                disabled={categories.links.next === null}
                                asChild={categories.links.next !== null}
                            >
                                {categories.links.next ? (
                                    <Link href={categories.links.next}>
                                        Next
                                    </Link>
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

ProductCategoryIndex.layout = {
    breadcrumbs: [
        {
            title: 'Product categories',
            href: ProductCategoryController.index(),
        },
    ],
};
