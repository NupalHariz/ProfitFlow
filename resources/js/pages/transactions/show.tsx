import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    Clock3,
    Pencil,
    ReceiptText,
    UserRound,
} from 'lucide-react';
import TransactionController from '@/actions/App/Http/Controllers/Transactions/TransactionController';
import TransactionStatusBadge from '@/components/transactions/transaction-status-badge';
import TransactionSummary from '@/components/transactions/transaction-summary';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatRupiah } from '@/lib/money';
import type { TransactionDetail } from '@/types';

export default function ShowTransaction({
    transaction,
}: {
    transaction: TransactionDetail;
}) {
    return (
        <>
            <Head title={transaction.transaction_number} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                    <div>
                        <Button asChild variant="ghost" className="mb-2 -ml-3">
                            <Link href={TransactionController.index()}>
                                <ArrowLeft />
                                Transactions
                            </Link>
                        </Button>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="font-mono text-2xl font-semibold tracking-tight">
                                {transaction.transaction_number}
                            </h1>
                            <TransactionStatusBadge
                                status={transaction.status}
                            />
                        </div>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Stored transaction snapshots and exact financial
                            totals.
                        </p>
                    </div>
                    {transaction.can_edit && (
                        <Button asChild>
                            <Link
                                href={TransactionController.edit(
                                    transaction.id,
                                )}
                            >
                                <Pencil />
                                Edit draft
                            </Link>
                        </Button>
                    )}
                </div>

                <Card>
                    <CardContent className="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                        <HeaderValue
                            icon={UserRound}
                            label="Customer"
                            value={transaction.customer.name}
                            detail={
                                transaction.customer.is_deleted
                                    ? 'Deleted customer record'
                                    : (transaction.customer.email ??
                                      transaction.customer.phone ??
                                      'No contact details')
                            }
                        />
                        <HeaderValue
                            icon={CalendarDays}
                            label="Transaction date"
                            value={formatDate(transaction.transaction_date)}
                        />
                        <HeaderValue
                            icon={ReceiptText}
                            label="Created by"
                            value={transaction.creator.name}
                            detail={transaction.creator.email}
                        />
                        <HeaderValue
                            icon={Clock3}
                            label="Created"
                            value={formatDateTime(transaction.created_at)}
                        />
                    </CardContent>
                </Card>

                <TransactionSummary
                    title="Financial totals"
                    preview={{
                        state: 'complete',
                        totalRevenue: transaction.total_revenue,
                        totalProductionCost: transaction.total_production_cost,
                        netProfit: transaction.total_net_profit,
                    }}
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Item snapshots</CardTitle>
                        <CardDescription>
                            These historical values are stored on the
                            transaction and are not reloaded from current
                            product master data.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full min-w-[960px] text-sm">
                                <thead className="border-b bg-muted/40 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-4 py-3">Snapshot</th>
                                        <th className="px-4 py-3 text-right">
                                            Qty
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Unit selling
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Unit cost
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Revenue
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Cost
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Net profit
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {transaction.items.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-4 py-4">
                                                <p className="font-medium">
                                                    {item.product_name_snapshot}
                                                </p>
                                                <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                    {item.product_sku_snapshot}{' '}
                                                    ·{' '}
                                                    {item.category_name_snapshot ??
                                                        'No category'}
                                                </p>
                                            </td>
                                            <td className="px-4 py-4 text-right tabular-nums">
                                                {item.quantity}
                                            </td>
                                            <td className="px-4 py-4 text-right tabular-nums">
                                                {formatRupiah(
                                                    item.unit_selling_price,
                                                )}
                                            </td>
                                            <td className="px-4 py-4 text-right tabular-nums">
                                                {formatRupiah(
                                                    item.unit_production_cost,
                                                )}
                                            </td>
                                            <td className="px-4 py-4 text-right tabular-nums">
                                                {formatRupiah(
                                                    item.total_revenue,
                                                )}
                                            </td>
                                            <td className="px-4 py-4 text-right tabular-nums">
                                                {formatRupiah(
                                                    item.total_production_cost,
                                                )}
                                            </td>
                                            <td className="px-4 py-4 text-right font-medium tabular-nums">
                                                {formatRupiah(item.net_profit)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {!transaction.can_edit && (
                    <LifecycleCard transaction={transaction} />
                )}
            </div>
        </>
    );
}

function HeaderValue({
    icon: Icon,
    label,
    value,
    detail,
}: {
    icon: typeof UserRound;
    label: string;
    value: string;
    detail?: string;
}) {
    return (
        <div className="flex gap-3">
            <Icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <div>
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="mt-1 font-medium">{value}</p>
                {detail && (
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {detail}
                    </p>
                )}
            </div>
        </div>
    );
}

function LifecycleCard({ transaction }: { transaction: TransactionDetail }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Read-only lifecycle</CardTitle>
                <CardDescription>
                    This transaction is no longer a draft and cannot be edited.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <LifecycleValue
                    label="Completed"
                    value={nullableDateTime(transaction.completed_at)}
                />
                <LifecycleValue
                    label="Distribution started"
                    value={nullableDateTime(
                        transaction.distribution_started_at,
                    )}
                />
                <LifecycleValue
                    label="Distributed"
                    value={nullableDateTime(transaction.distributed_at)}
                />
                <LifecycleValue
                    label="Cancelled"
                    value={nullableDateTime(transaction.cancelled_at)}
                />
                {transaction.failure_reason && (
                    <div className="sm:col-span-2 lg:col-span-4">
                        <p className="text-xs text-muted-foreground">
                            Failure reason
                        </p>
                        <p className="mt-1 text-sm">
                            {transaction.failure_reason}
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function LifecycleValue({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-sm font-medium">{value}</p>
        </div>
    );
}

function nullableDateTime(value: string | null): string {
    return value === null ? 'Not recorded' : formatDateTime(value);
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

ShowTransaction.layout = {
    breadcrumbs: [
        {
            title: 'Transactions',
            href: TransactionController.index(),
        },
    ],
};
