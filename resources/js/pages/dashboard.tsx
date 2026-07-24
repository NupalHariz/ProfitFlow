import { Head, Link } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowRight,
    CircleDollarSign,
    Clock3,
    HandCoins,
    Plus,
    ReceiptText,
    Scale,
    TimerReset,
    TrendingDown,
    TrendingUp,
    UsersRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import TransactionController from '@/actions/App/Http/Controllers/Transactions/TransactionController';
import ProfitRecipientTypeBadge from '@/components/profit-recipients/profit-recipient-type-badge';
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
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type {
    DashboardFailedDistribution,
    DashboardPageProps,
    DashboardPendingDistribution,
    DashboardRecentTransaction,
    TransactionStatus,
} from '@/types';

const statusOrder: TransactionStatus[] = [
    'draft',
    'pending_distribution',
    'processing_distribution',
    'distributed',
    'distribution_failed',
    'cancelled',
];

const statusLabels: Record<TransactionStatus, string> = {
    draft: 'Draft',
    pending_distribution: 'Pending',
    processing_distribution: 'Processing',
    distributed: 'Distributed',
    distribution_failed: 'Failed',
    cancelled: 'Cancelled',
};

const statusDescriptions: Record<TransactionStatus, string> = {
    draft: 'Still editable',
    pending_distribution: 'Waiting for worker',
    processing_distribution: 'Worker in progress',
    distributed: 'Ledger complete',
    distribution_failed: 'Needs review',
    cancelled: 'No longer active',
};

export default function Dashboard({
    financialSummary,
    statusSummary,
    recentTransactions,
    recentFailedDistributions,
    recentPendingDistributions,
    topRecipients,
}: DashboardPageProps) {
    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Dashboard
                        </h1>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                            Finalized business performance, distribution
                            activity, and transaction health at a glance.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={TransactionController.create()}>
                            <Plus />
                            Create transaction
                        </Link>
                    </Button>
                </div>

                <section aria-labelledby="financial-overview-title">
                    <div className="mb-3 flex items-end justify-between gap-4">
                        <div>
                            <h2
                                id="financial-overview-title"
                                className="text-base font-semibold"
                            >
                                Financial overview
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Finalized, non-cancelled transactions and the
                                immutable distribution ledger.
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <FinancialCard
                            label="Total revenue"
                            value={formatRupiah(financialSummary.totalRevenue)}
                            detail="Finalized transactions"
                            icon={TrendingUp}
                            tone="emerald"
                        />
                        <FinancialCard
                            label="Production cost"
                            value={formatRupiah(
                                financialSummary.totalProductionCost,
                            )}
                            detail="Finalized transactions"
                            icon={TrendingDown}
                            tone="amber"
                        />
                        <FinancialCard
                            label="Net profit"
                            value={formatRupiah(
                                financialSummary.totalNetProfit,
                            )}
                            detail="May include transaction losses"
                            icon={Scale}
                            tone={
                                financialSummary.totalNetProfit.startsWith('-')
                                    ? 'red'
                                    : 'blue'
                            }
                            negative={financialSummary.totalNetProfit.startsWith(
                                '-',
                            )}
                        />
                        <FinancialCard
                            label="Distributed profit"
                            value={formatRupiah(
                                financialSummary.totalDistributedProfit,
                            )}
                            detail="Immutable ledger rows"
                            icon={HandCoins}
                            tone="violet"
                        />
                        <FinancialCard
                            label="Transactions"
                            value={financialSummary.totalTransactions.toLocaleString(
                                'en-US',
                            )}
                            detail="Every lifecycle status"
                            icon={ReceiptText}
                            tone="slate"
                        />
                    </div>
                    <p className="mt-3 text-xs text-muted-foreground">
                        Net profit and distributed profit are separate measures.
                        Loss-making items create no distributions, so the totals
                        are not expected to match.
                    </p>
                </section>

                <section aria-labelledby="status-summary-title">
                    <div className="mb-3">
                        <h2
                            id="status-summary-title"
                            className="text-base font-semibold"
                        >
                            Transaction lifecycle
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Select a status to review its filtered transaction
                            list.
                        </p>
                    </div>
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                        {statusOrder.map((status) => (
                            <Link
                                key={status}
                                href={TransactionController.index({
                                    query: { status },
                                })}
                                className="group rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <Card className="h-full gap-4 py-5 transition-colors group-hover:border-foreground/20 group-hover:bg-muted/30">
                                    <CardHeader className="px-5">
                                        <div className="flex items-start justify-between gap-3">
                                            <TransactionStatusBadge
                                                status={status}
                                            />
                                            <span className="text-2xl font-semibold tabular-nums">
                                                {statusSummary[status]}
                                            </span>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="px-5">
                                        <p className="font-medium">
                                            {statusLabels[status]}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {statusDescriptions[status]}
                                        </p>
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                </section>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1.65fr)_minmax(20rem,0.85fr)]">
                    <RecentTransactions transactions={recentTransactions} />
                    <TopRecipients recipients={topRecipients} />
                </div>

                <section aria-labelledby="attention-title">
                    <div className="mb-3">
                        <h2
                            id="attention-title"
                            className="text-base font-semibold"
                        >
                            Needs attention
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Recent failures and the oldest transactions still
                            waiting for the critical worker.
                        </p>
                    </div>
                    <div className="grid gap-4 xl:grid-cols-2">
                        <FailedDistributions
                            transactions={recentFailedDistributions}
                        />
                        <PendingDistributions
                            transactions={recentPendingDistributions}
                        />
                    </div>
                </section>
            </div>
        </>
    );
}

function FinancialCard({
    label,
    value,
    detail,
    icon: Icon,
    tone,
    negative = false,
}: {
    label: string;
    value: string;
    detail: string;
    icon: LucideIcon;
    tone: 'amber' | 'blue' | 'emerald' | 'red' | 'slate' | 'violet';
    negative?: boolean;
}) {
    const tones = {
        amber: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
        blue: 'bg-blue-500/10 text-blue-700 dark:text-blue-300',
        emerald: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        red: 'bg-red-500/10 text-red-700 dark:text-red-300',
        slate: 'bg-slate-500/10 text-slate-700 dark:text-slate-300',
        violet: 'bg-violet-500/10 text-violet-700 dark:text-violet-300',
    };

    return (
        <Card className="gap-4 py-5">
            <CardHeader className="flex-row items-start justify-between gap-3 px-5">
                <div>
                    <CardDescription>{label}</CardDescription>
                    <CardTitle
                        className={cn(
                            'mt-2 text-xl leading-tight tabular-nums',
                            negative && 'text-red-700 dark:text-red-300',
                        )}
                    >
                        {value}
                    </CardTitle>
                </div>
                <span
                    className={cn(
                        'flex size-9 shrink-0 items-center justify-center rounded-lg',
                        tones[tone],
                    )}
                >
                    <Icon className="size-4" />
                </span>
            </CardHeader>
            <CardContent className="px-5">
                <p className="text-xs text-muted-foreground">{detail}</p>
            </CardContent>
        </Card>
    );
}

function RecentTransactions({
    transactions,
}: {
    transactions: DashboardRecentTransaction[];
}) {
    return (
        <Card className="overflow-hidden py-0">
            <CardHeader className="flex-row items-center justify-between gap-4 border-b py-5">
                <div>
                    <CardTitle>Recent transactions</CardTitle>
                    <CardDescription className="mt-1">
                        Latest by transaction date
                    </CardDescription>
                </div>
                <Button asChild variant="ghost" size="sm">
                    <Link href={TransactionController.index()}>
                        View all
                        <ArrowRight />
                    </Link>
                </Button>
            </CardHeader>
            {transactions.length === 0 ? (
                <EmptyState
                    icon={ReceiptText}
                    title="No transactions yet"
                    description="Create the first transaction to begin tracking financial activity."
                />
            ) : (
                <>
                    <div className="grid divide-y lg:hidden">
                        {transactions.map((transaction) => (
                            <RecentTransactionMobile
                                key={transaction.id}
                                transaction={transaction}
                            />
                        ))}
                    </div>
                    <div className="hidden overflow-x-auto lg:block">
                        <table className="w-full min-w-[760px] text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-5 py-3">Transaction</th>
                                    <th className="px-5 py-3">Customer</th>
                                    <th className="px-5 py-3">Status</th>
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
                                {transactions.map((transaction) => (
                                    <tr
                                        key={transaction.id}
                                        className="hover:bg-muted/30"
                                    >
                                        <td className="px-5 py-4">
                                            <p className="font-mono font-medium">
                                                {transaction.transaction_number}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {formatDate(
                                                    transaction.transaction_date,
                                                )}
                                            </p>
                                        </td>
                                        <td className="px-5 py-4">
                                            {transaction.customer.name}
                                        </td>
                                        <td className="px-5 py-4">
                                            <TransactionStatusBadge
                                                status={transaction.status}
                                            />
                                        </td>
                                        <td className="px-5 py-4 text-right font-medium tabular-nums">
                                            {formatRupiah(
                                                transaction.total_revenue,
                                            )}
                                        </td>
                                        <td
                                            className={cn(
                                                'px-5 py-4 text-right font-medium tabular-nums',
                                                transaction.total_net_profit.startsWith(
                                                    '-',
                                                ) &&
                                                    'text-red-700 dark:text-red-300',
                                            )}
                                        >
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
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </Card>
    );
}

function RecentTransactionMobile({
    transaction,
}: {
    transaction: DashboardRecentTransaction;
}) {
    return (
        <div className="space-y-4 p-5">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="font-mono font-medium">
                        {transaction.transaction_number}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {transaction.customer.name} ·{' '}
                        {formatDate(transaction.transaction_date)}
                    </p>
                </div>
                <TransactionStatusBadge status={transaction.status} />
            </div>
            <div className="grid grid-cols-2 gap-3 text-sm">
                <Metric
                    label="Revenue"
                    value={formatRupiah(transaction.total_revenue)}
                />
                <Metric
                    label="Net profit"
                    value={formatRupiah(transaction.total_net_profit)}
                    negative={transaction.total_net_profit.startsWith('-')}
                />
            </div>
            <Button asChild variant="outline" size="sm" className="w-full">
                <Link href={TransactionController.show(transaction.id)}>
                    View transaction
                    <ArrowRight />
                </Link>
            </Button>
        </div>
    );
}

function TopRecipients({
    recipients,
}: {
    recipients: DashboardPageProps['topRecipients'];
}) {
    return (
        <Card className="overflow-hidden py-0">
            <CardHeader className="border-b py-5">
                <div className="flex items-center gap-3">
                    <span className="flex size-9 items-center justify-center rounded-lg bg-violet-500/10 text-violet-700 dark:text-violet-300">
                        <UsersRound className="size-4" />
                    </span>
                    <div>
                        <CardTitle>Top Profit Recipients</CardTitle>
                        <CardDescription className="mt-1">
                            Ranked by immutable distributed amount
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            {recipients.length === 0 ? (
                <EmptyState
                    icon={CircleDollarSign}
                    title="No distributions yet"
                    description="Recipient rankings appear after immutable ledger rows are created."
                />
            ) : (
                <div className="divide-y">
                    {recipients.map((recipient, index) => (
                        <div
                            key={recipient.id}
                            className="flex items-center gap-3 px-5 py-4"
                        >
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-semibold tabular-nums">
                                {index + 1}
                            </span>
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="truncate font-medium">
                                        {recipient.display_name}
                                    </p>
                                    <ProfitRecipientTypeBadge
                                        type={recipient.type}
                                    />
                                </div>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {recipient.contributing_transaction_count}{' '}
                                    {recipient.contributing_transaction_count ===
                                    1
                                        ? 'transaction'
                                        : 'transactions'}
                                </p>
                            </div>
                            <p className="shrink-0 text-sm font-semibold tabular-nums">
                                {formatRupiah(recipient.total_amount)}
                            </p>
                        </div>
                    ))}
                </div>
            )}
        </Card>
    );
}

function FailedDistributions({
    transactions,
}: {
    transactions: DashboardFailedDistribution[];
}) {
    return (
        <Card className="overflow-hidden border-red-500/20 py-0">
            <CardHeader className="border-b border-red-500/15 bg-red-500/[0.03] py-5">
                <div className="flex items-start gap-3">
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-red-500/10 text-red-700 dark:text-red-300">
                        <AlertCircle className="size-4" />
                    </span>
                    <div>
                        <CardTitle>Recent distribution failures</CardTitle>
                        <CardDescription className="mt-1">
                            Review the transaction detail before deciding
                            whether to retry.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            {transactions.length === 0 ? (
                <EmptyState
                    icon={AlertCircle}
                    title="No recent failures"
                    description="There are no failed distributions requiring review."
                    compact
                />
            ) : (
                <div className="divide-y">
                    {transactions.map((transaction) => (
                        <Link
                            key={transaction.id}
                            href={TransactionController.show(transaction.id)}
                            className="flex items-start gap-3 px-5 py-4 transition-colors hover:bg-muted/30"
                        >
                            <AlertCircle className="mt-0.5 size-4 shrink-0 text-red-600 dark:text-red-400" />
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <p className="font-mono text-sm font-medium">
                                        {transaction.transaction_number}
                                    </p>
                                    <span className="text-xs text-muted-foreground">
                                        {formatDateTime(transaction.updated_at)}
                                    </span>
                                </div>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {transaction.customer.name} ·{' '}
                                    {formatDate(transaction.transaction_date)}
                                </p>
                                <p className="mt-2 line-clamp-2 text-sm">
                                    {transaction.failure_reason ??
                                        'No failure reason was recorded.'}
                                </p>
                            </div>
                            <ArrowRight className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        </Link>
                    ))}
                </div>
            )}
        </Card>
    );
}

function PendingDistributions({
    transactions,
}: {
    transactions: DashboardPendingDistribution[];
}) {
    return (
        <Card className="overflow-hidden border-amber-500/20 py-0">
            <CardHeader className="border-b border-amber-500/15 bg-amber-500/[0.03] py-5">
                <div className="flex items-start gap-3">
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-700 dark:text-amber-300">
                        <TimerReset className="size-4" />
                    </span>
                    <div>
                        <CardTitle>Oldest pending distributions</CardTitle>
                        <CardDescription className="mt-1">
                            These transactions may still be waiting for the
                            critical queue worker.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            {transactions.length === 0 ? (
                <EmptyState
                    icon={Clock3}
                    title="No pending distributions"
                    description="There are no completed transactions waiting for distribution."
                    compact
                />
            ) : (
                <div className="divide-y">
                    {transactions.map((transaction) => (
                        <Link
                            key={transaction.id}
                            href={TransactionController.show(transaction.id)}
                            className="flex items-center gap-3 px-5 py-4 transition-colors hover:bg-muted/30"
                        >
                            <Clock3 className="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                            <div className="min-w-0 flex-1">
                                <p className="font-mono text-sm font-medium">
                                    {transaction.transaction_number}
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {transaction.customer.name}
                                </p>
                            </div>
                            <div className="shrink-0 text-right">
                                <p className="text-xs text-muted-foreground">
                                    Completed
                                </p>
                                <p className="mt-1 text-xs font-medium">
                                    {formatDateTime(transaction.completed_at)}
                                </p>
                            </div>
                            <ArrowRight className="size-4 shrink-0 text-muted-foreground" />
                        </Link>
                    ))}
                </div>
            )}
        </Card>
    );
}

function EmptyState({
    icon: Icon,
    title,
    description,
    compact = false,
}: {
    icon: LucideIcon;
    title: string;
    description: string;
    compact?: boolean;
}) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center px-6 text-center',
                compact ? 'py-8' : 'py-12',
            )}
        >
            <span className="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                <Icon className="size-5" />
            </span>
            <p className="mt-3 font-medium">{title}</p>
            <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                {description}
            </p>
        </div>
    );
}

function Metric({
    label,
    value,
    negative = false,
}: {
    label: string;
    value: string;
    negative?: boolean;
}) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p
                className={cn(
                    'mt-1 font-medium tabular-nums',
                    negative && 'text-red-700 dark:text-red-300',
                )}
            >
                {value}
            </p>
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

function formatDateTime(value: string | null): string {
    if (value === null) {
        return 'Unknown';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
