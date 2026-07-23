import { Form, Head, Link } from '@inertiajs/react';
import { Eye, Pencil, Plus, Search } from 'lucide-react';
import CustomerController from '@/actions/App/Http/Controllers/Customers/CustomerController';
import DeleteCustomerDialog from '@/components/customers/delete-customer-dialog';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type {
    CustomerFilters,
    CustomerPagination,
    CustomerSummary,
} from '@/types';

type PageProps = {
    filters: CustomerFilters;
    customers: CustomerPagination;
};

export default function CustomerIndex({ filters, customers }: PageProps) {
    return (
        <>
            <Head title="Customers" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Customers
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Maintain Customer contact details and review their
                            related transactions.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={CustomerController.create()}>
                            <Plus />
                            Create Customer
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardContent>
                        <Form
                            {...CustomerController.index.form()}
                            className="flex flex-col gap-3 sm:flex-row"
                        >
                            <div className="relative flex-1">
                                <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                <Input
                                    name="search"
                                    defaultValue={filters.search}
                                    placeholder="Search name, email, or phone"
                                    className="pl-9"
                                />
                            </div>
                            <Button>Search</Button>
                            {filters.search !== '' && (
                                <Button variant="ghost" asChild>
                                    <Link href={CustomerController.index()}>
                                        Clear
                                    </Link>
                                </Button>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {customers.data.length === 0 ? (
                    <Card className="border-dashed">
                        <CardHeader className="items-center py-12 text-center">
                            <CardTitle>No Customers found</CardTitle>
                            <CardDescription>
                                Adjust the search or create the first Customer.
                            </CardDescription>
                            <Button asChild className="mt-3">
                                <Link href={CustomerController.create()}>
                                    <Plus />
                                    Create Customer
                                </Link>
                            </Button>
                        </CardHeader>
                    </Card>
                ) : (
                    <>
                        <div className="grid gap-3 lg:hidden">
                            {customers.data.map((customer) => (
                                <Card key={customer.id}>
                                    <CardHeader>
                                        <CardTitle>{customer.name}</CardTitle>
                                        <CardDescription>
                                            {contactSummary(customer)}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="grid grid-cols-2 gap-3 text-sm">
                                            <div>
                                                <p className="text-xs text-muted-foreground">
                                                    Transactions
                                                </p>
                                                <p className="mt-1 font-medium">
                                                    {
                                                        customer.transactions_count
                                                    }
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs text-muted-foreground">
                                                    Updated
                                                </p>
                                                <p className="mt-1 font-medium">
                                                    {formatDate(
                                                        customer.updated_at,
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                        {customer.address && (
                                            <p className="line-clamp-2 text-sm text-muted-foreground">
                                                {customer.address}
                                            </p>
                                        )}
                                        <CustomerActions customer={customer} />
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
                                                Customer
                                            </th>
                                            <th className="px-5 py-3">
                                                Contact
                                            </th>
                                            <th className="px-5 py-3">
                                                Transactions
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
                                        {customers.data.map((customer) => (
                                            <tr
                                                key={customer.id}
                                                className="hover:bg-muted/30"
                                            >
                                                <td className="max-w-sm px-5 py-4">
                                                    <p className="font-medium">
                                                        {customer.name}
                                                    </p>
                                                    {customer.address && (
                                                        <p className="mt-1 line-clamp-1 text-xs text-muted-foreground">
                                                            {customer.address}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <p>
                                                        {customer.email ??
                                                            'No email'}
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {customer.phone ??
                                                            'No phone'}
                                                    </p>
                                                </td>
                                                <td className="px-5 py-4 tabular-nums">
                                                    {
                                                        customer.transactions_count
                                                    }
                                                </td>
                                                <td className="px-5 py-4 whitespace-nowrap text-muted-foreground">
                                                    {formatDate(
                                                        customer.updated_at,
                                                    )}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <CustomerActions
                                                        customer={customer}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Card>

                        <Pagination customers={customers} />
                    </>
                )}
            </div>
        </>
    );
}

function CustomerActions({ customer }: { customer: CustomerSummary }) {
    return (
        <div className="flex flex-wrap gap-2">
            <Button asChild variant="outline" size="sm">
                <Link href={CustomerController.show(customer.id)}>
                    <Eye />
                    View
                </Link>
            </Button>
            <Button asChild variant="outline" size="sm">
                <Link href={CustomerController.edit(customer.id)}>
                    <Pencil />
                    Edit
                </Link>
            </Button>
            <DeleteCustomerDialog
                customerId={customer.id}
                customerName={customer.name}
            />
        </div>
    );
}

function Pagination({ customers }: { customers: CustomerPagination }) {
    return (
        <div className="flex flex-col justify-between gap-3 text-sm sm:flex-row sm:items-center">
            <p className="text-muted-foreground">
                Showing {customers.meta.from ?? 0}–{customers.meta.to ?? 0} of{' '}
                {customers.meta.total}
            </p>
            <div className="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={customers.links.previous === null}
                    asChild={customers.links.previous !== null}
                >
                    {customers.links.previous ? (
                        <Link href={customers.links.previous}>Previous</Link>
                    ) : (
                        <span>Previous</span>
                    )}
                </Button>
                {customers.links.pages.map((link) => (
                    <Button
                        key={link.page}
                        variant={link.active ? 'default' : 'outline'}
                        size="sm"
                        asChild
                    >
                        <Link href={link.url}>{link.page}</Link>
                    </Button>
                ))}
                <Button
                    variant="outline"
                    size="sm"
                    disabled={customers.links.next === null}
                    asChild={customers.links.next !== null}
                >
                    {customers.links.next ? (
                        <Link href={customers.links.next}>Next</Link>
                    ) : (
                        <span>Next</span>
                    )}
                </Button>
            </div>
        </div>
    );
}

function contactSummary(customer: CustomerSummary): string {
    return customer.email ?? customer.phone ?? 'No contact details';
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

CustomerIndex.layout = {
    breadcrumbs: [
        {
            title: 'Customers',
            href: CustomerController.index(),
        },
    ],
};
