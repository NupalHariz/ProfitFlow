import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    CalendarDays,
    Mail,
    MapPin,
    Pencil,
    Phone,
    ReceiptText,
} from 'lucide-react';
import CustomerController from '@/actions/App/Http/Controllers/Customers/CustomerController';
import TransactionController from '@/actions/App/Http/Controllers/Transactions/TransactionController';
import DeleteCustomerDialog from '@/components/customers/delete-customer-dialog';
import TransactionStatusBadge from '@/components/transactions/transaction-status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatRupiah } from '@/lib/money';
import type {
    CustomerFormData,
    CustomerRecentTransaction,
    CustomerTransactionSummary,
} from '@/types';

type PageProps = {
    customer: CustomerFormData;
    transaction_summary: CustomerTransactionSummary;
    recent_transactions: CustomerRecentTransaction[];
};

export default function ShowCustomer({
    customer,
    transaction_summary: transactionSummary,
    recent_transactions: recentTransactions,
}: PageProps) {
    return (
        <>
            <Head title={customer.name} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                    <div>
                        <Button asChild variant="ghost" className="mb-2 -ml-3">
                            <Link href={CustomerController.index()}>
                                <ArrowLeft />
                                Customers
                            </Link>
                        </Button>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {customer.name}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Current Customer profile and bounded transaction
                            history.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
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
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Customer profile</CardTitle>
                        <CardDescription>
                            Transactions reference this live record; Customer
                            fields are not immutable transaction snapshots.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                        <ProfileValue
                            icon={Mail}
                            label="Email"
                            value={customer.email ?? 'Not provided'}
                        />
                        <ProfileValue
                            icon={Phone}
                            label="Phone"
                            value={customer.phone ?? 'Not provided'}
                        />
                        <ProfileValue
                            icon={MapPin}
                            label="Address"
                            value={customer.address ?? 'Not provided'}
                        />
                        <ProfileValue
                            icon={CalendarDays}
                            label="Record dates"
                            value={`Created ${formatDateTime(customer.created_at)}`}
                            detail={`Updated ${formatDateTime(customer.updated_at)}`}
                        />
                    </CardContent>
                </Card>

                <div className="grid gap-4 sm:grid-cols-3">
                    <SummaryCard
                        label="All transactions"
                        value={transactionSummary.total_count}
                    />
                    <SummaryCard
                        label="Draft"
                        value={transactionSummary.draft_count}
                    />
                    <SummaryCard
                        label="Distributed"
                        value={transactionSummary.distributed_count}
                    />
                </div>

                <Card>
                    <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <CardTitle>Recent transactions</CardTitle>
                            <CardDescription>
                                The 10 newest records by transaction date and
                                ID, using stored totals.
                            </CardDescription>
                        </div>
                        <Button asChild variant="outline" size="sm">
                            <Link
                                href={TransactionController.index({
                                    query: { customer_id: customer.id },
                                })}
                            >
                                View all
                                <ArrowRight />
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {recentTransactions.length === 0 ? (
                            <div className="flex flex-col items-center rounded-lg border border-dashed py-10 text-center">
                                <ReceiptText className="size-8 text-muted-foreground" />
                                <p className="mt-3 font-medium">
                                    No transactions yet
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    This Customer has not been used by a
                                    transaction.
                                </p>
                            </div>
                        ) : (
                            <>
                                <div className="grid gap-3 lg:hidden">
                                    {recentTransactions.map((transaction) => (
                                        <Card key={transaction.id}>
                                            <CardHeader>
                                                <div className="flex items-start justify-between gap-3">
                                                    <div>
                                                        <CardTitle className="font-mono text-base">
                                                            {
                                                                transaction.transaction_number
                                                            }
                                                        </CardTitle>
                                                        <CardDescription>
                                                            {formatDate(
                                                                transaction.transaction_date,
                                                            )}
                                                        </CardDescription>
                                                    </div>
                                                    <TransactionStatusBadge
                                                        status={
                                                            transaction.status
                                                        }
                                                    />
                                                </div>
                                            </CardHeader>
                                            <CardContent className="space-y-3">
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
                                                        View transaction
                                                        <ArrowRight />
                                                    </Link>
                                                </Button>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>

                                <div className="hidden overflow-x-auto rounded-lg border lg:block">
                                    <table className="w-full text-sm">
                                        <thead className="border-b bg-muted/40 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            <tr>
                                                <th className="px-4 py-3">
                                                    Transaction
                                                </th>
                                                <th className="px-4 py-3">
                                                    Date
                                                </th>
                                                <th className="px-4 py-3">
                                                    Status
                                                </th>
                                                <th className="px-4 py-3 text-right">
                                                    Revenue
                                                </th>
                                                <th className="px-4 py-3 text-right">
                                                    Net profit
                                                </th>
                                                <th className="px-4 py-3 text-right">
                                                    Action
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {recentTransactions.map(
                                                (transaction) => (
                                                    <tr key={transaction.id}>
                                                        <td className="px-4 py-4 font-mono font-medium">
                                                            {
                                                                transaction.transaction_number
                                                            }
                                                        </td>
                                                        <td className="px-4 py-4">
                                                            {formatDate(
                                                                transaction.transaction_date,
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-4">
                                                            <TransactionStatusBadge
                                                                status={
                                                                    transaction.status
                                                                }
                                                            />
                                                        </td>
                                                        <td className="px-4 py-4 text-right font-medium tabular-nums">
                                                            {formatRupiah(
                                                                transaction.total_revenue,
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-4 text-right font-medium tabular-nums">
                                                            {formatRupiah(
                                                                transaction.total_net_profit,
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-4 text-right">
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
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function ProfileValue({
    icon: Icon,
    label,
    value,
    detail,
}: {
    icon: typeof Mail;
    label: string;
    value: string;
    detail?: string;
}) {
    return (
        <div className="flex gap-3">
            <Icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <div className="min-w-0">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="mt-1 font-medium break-words whitespace-pre-line">
                    {value}
                </p>
                {detail && (
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {detail}
                    </p>
                )}
            </div>
        </div>
    );
}

function SummaryCard({ label, value }: { label: string; value: number }) {
    return (
        <Card>
            <CardContent>
                <p className="text-sm text-muted-foreground">{label}</p>
                <p className="mt-2 text-2xl font-semibold tabular-nums">
                    {value}
                </p>
            </CardContent>
        </Card>
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

function formatDateTime(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

ShowCustomer.layout = {
    breadcrumbs: [
        {
            title: 'Customers',
            href: CustomerController.index(),
        },
    ],
};
