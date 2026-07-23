import { Form, Head, Link } from '@inertiajs/react';
import { ArrowRight, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import TransactionController from '@/actions/App/Http/Controllers/Transactions/TransactionController';
import TransactionStatusBadge from '@/components/transactions/transaction-status-badge';
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
import type { TransactionCustomerLookup, TransactionStatus } from '@/types';

type TransactionRow = {
    id: number;
    transaction_number: string;
    transaction_date: string;
    status: TransactionStatus;
    customer: {
        id: number;
        name: string;
        is_deleted: boolean;
    };
    total_revenue: string;
    total_production_cost: string;
    total_net_profit: string;
    item_count: number;
    created_at: string;
};

type PageProps = {
    filters: {
        search: string;
        status: TransactionStatus | null;
        date_from: string | null;
        date_until: string | null;
        customer_id: number | null;
    };
    customer_options: TransactionCustomerLookup[];
    transactions: {
        data: TransactionRow[];
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
};

export default function TransactionIndex({
    filters,
    customer_options,
    transactions,
}: PageProps) {
    const [status, setStatus] = useState(filters.status ?? 'all');
    const [customerId, setCustomerId] = useState(
        filters.customer_id?.toString() ?? 'all',
    );
    const hasFilters =
        filters.search !== '' ||
        filters.status !== null ||
        filters.date_from !== null ||
        filters.date_until !== null ||
        filters.customer_id !== null;

    return (
        <>
            <Head title="Transactions" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Transactions
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Create draft sales and review their stored financial
                            snapshots.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={TransactionController.create()}>
                            <Plus />
                            Create transaction
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardContent>
                        <Form
                            {...TransactionController.index.form()}
                            className="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(14rem,1fr)_12rem_12rem_12rem_12rem_auto_auto]"
                        >
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                <Input
                                    name="search"
                                    defaultValue={filters.search}
                                    placeholder="Number or customer"
                                    className="pl-9"
                                />
                            </div>
                            <input
                                type="hidden"
                                name="status"
                                value={status === 'all' ? '' : status}
                            />
                            <Select
                                value={status}
                                onValueChange={(value) =>
                                    setStatus(
                                        value as TransactionStatus | 'all',
                                    )
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All statuses
                                    </SelectItem>
                                    <SelectItem value="draft">Draft</SelectItem>
                                    <SelectItem value="pending_distribution">
                                        Pending distribution
                                    </SelectItem>
                                    <SelectItem value="processing_distribution">
                                        Processing
                                    </SelectItem>
                                    <SelectItem value="distributed">
                                        Distributed
                                    </SelectItem>
                                    <SelectItem value="distribution_failed">
                                        Distribution failed
                                    </SelectItem>
                                    <SelectItem value="cancelled">
                                        Cancelled
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Input
                                name="date_from"
                                type="date"
                                defaultValue={filters.date_from ?? ''}
                                aria-label="Date from"
                            />
                            <Input
                                name="date_until"
                                type="date"
                                defaultValue={filters.date_until ?? ''}
                                aria-label="Date until"
                            />
                            <input
                                type="hidden"
                                name="customer_id"
                                value={customerId === 'all' ? '' : customerId}
                            />
                            <Select
                                value={customerId}
                                onValueChange={setCustomerId}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All customers
                                    </SelectItem>
                                    {customer_options.map((customer) => (
                                        <SelectItem
                                            key={customer.id}
                                            value={customer.id.toString()}
                                        >
                                            {customer.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button>Apply</Button>
                            {hasFilters && (
                                <Button variant="ghost" asChild>
                                    <Link href={TransactionController.index()}>
                                        Clear
                                    </Link>
                                </Button>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {transactions.data.length === 0 ? (
                    <Card className="border-dashed">
                        <CardHeader className="items-center py-12 text-center">
                            <CardTitle>No transactions found</CardTitle>
                            <CardDescription>
                                Adjust the filters or create the first draft.
                            </CardDescription>
                            <Button asChild className="mt-3">
                                <Link href={TransactionController.create()}>
                                    <Plus />
                                    Create transaction
                                </Link>
                            </Button>
                        </CardHeader>
                    </Card>
                ) : (
                    <>
                        <div className="grid gap-3 lg:hidden">
                            {transactions.data.map((transaction) => (
                                <Card key={transaction.id}>
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <CardTitle className="font-mono">
                                                    {
                                                        transaction.transaction_number
                                                    }
                                                </CardTitle>
                                                <CardDescription className="mt-1">
                                                    <span className="inline-flex flex-wrap items-center gap-2">
                                                        {
                                                            transaction.customer
                                                                .name
                                                        }
                                                        {transaction.customer
                                                            .is_deleted && (
                                                            <Badge
                                                                variant="outline"
                                                                className="text-[0.65rem]"
                                                            >
                                                                Deleted Customer
                                                            </Badge>
                                                        )}
                                                        <span>
                                                            ·{' '}
                                                            {formatDate(
                                                                transaction.transaction_date,
                                                            )}
                                                        </span>
                                                    </span>
                                                </CardDescription>
                                            </div>
                                            <TransactionStatusBadge
                                                status={transaction.status}
                                            />
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="grid grid-cols-2 gap-3 text-sm">
                                            <div>
                                                <p className="text-xs text-muted-foreground">
                                                    Revenue
                                                </p>
                                                <p className="mt-1 font-medium">
                                                    {formatRupiah(
                                                        transaction.total_revenue,
                                                    )}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs text-muted-foreground">
                                                    Net profit
                                                </p>
                                                <p className="mt-1 font-medium">
                                                    {formatRupiah(
                                                        transaction.total_net_profit,
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-muted-foreground">
                                                {transaction.item_count}{' '}
                                                {transaction.item_count === 1
                                                    ? 'item'
                                                    : 'items'}
                                            </span>
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <Link
                                                    href={TransactionController.show(
                                                        transaction.id,
                                                    )}
                                                >
                                                    View
                                                    <ArrowRight />
                                                </Link>
                                            </Button>
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
                                                Transaction
                                            </th>
                                            <th className="px-5 py-3">
                                                Customer
                                            </th>
                                            <th className="px-5 py-3">
                                                Status
                                            </th>
                                            <th className="px-5 py-3">Items</th>
                                            <th className="px-5 py-3 text-right">
                                                Revenue
                                            </th>
                                            <th className="px-5 py-3 text-right">
                                                Net profit
                                            </th>
                                            <th className="px-5 py-3 text-right">
                                                Action
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {transactions.data.map(
                                            (transaction) => (
                                                <tr
                                                    key={transaction.id}
                                                    className="hover:bg-muted/30"
                                                >
                                                    <td className="px-5 py-4">
                                                        <p className="font-mono font-medium">
                                                            {
                                                                transaction.transaction_number
                                                            }
                                                        </p>
                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {formatDate(
                                                                transaction.transaction_date,
                                                            )}
                                                        </p>
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <span>
                                                                {
                                                                    transaction
                                                                        .customer
                                                                        .name
                                                                }
                                                            </span>
                                                            {transaction
                                                                .customer
                                                                .is_deleted && (
                                                                <Badge variant="outline">
                                                                    Deleted
                                                                    Customer
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        <TransactionStatusBadge
                                                            status={
                                                                transaction.status
                                                            }
                                                        />
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        {transaction.item_count}
                                                    </td>
                                                    <td className="px-5 py-4 text-right font-medium tabular-nums">
                                                        {formatRupiah(
                                                            transaction.total_revenue,
                                                        )}
                                                    </td>
                                                    <td className="px-5 py-4 text-right font-medium tabular-nums">
                                                        {formatRupiah(
                                                            transaction.total_net_profit,
                                                        )}
                                                    </td>
                                                    <td className="px-5 py-4 text-right">
                                                        <Button
                                                            asChild
                                                            variant="ghost"
                                                            size="sm"
                                                        >
                                                            <Link
                                                                href={TransactionController.show(
                                                                    transaction.id,
                                                                )}
                                                            >
                                                                View
                                                                <ArrowRight />
                                                            </Link>
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </Card>

                        <Pagination transactions={transactions} />
                    </>
                )}
            </div>
        </>
    );
}

function Pagination({
    transactions,
}: {
    transactions: PageProps['transactions'];
}) {
    return (
        <div className="flex flex-col justify-between gap-3 text-sm sm:flex-row sm:items-center">
            <p className="text-muted-foreground">
                Showing {transactions.meta.from ?? 0}–
                {transactions.meta.to ?? 0} of {transactions.meta.total}
            </p>
            <div className="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={transactions.links.previous === null}
                    asChild={transactions.links.previous !== null}
                >
                    {transactions.links.previous ? (
                        <Link href={transactions.links.previous}>Previous</Link>
                    ) : (
                        <span>Previous</span>
                    )}
                </Button>
                {transactions.links.pages.map((link) => (
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
                    disabled={transactions.links.next === null}
                    asChild={transactions.links.next !== null}
                >
                    {transactions.links.next ? (
                        <Link href={transactions.links.next}>Next</Link>
                    ) : (
                        <span>Next</span>
                    )}
                </Button>
            </div>
        </div>
    );
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}

TransactionIndex.layout = {
    breadcrumbs: [
        {
            title: 'Transactions',
            href: TransactionController.index(),
        },
    ],
};
