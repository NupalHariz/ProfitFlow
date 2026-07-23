import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatRupiah, moneyToSignedCents } from '@/lib/money';
import type { TransactionDetail } from '@/types';

export default function TransactionDistributionBreakdown({
    transaction,
}: {
    transaction: TransactionDetail;
}) {
    const hasRows = transaction.items.some(
        (item) => item.distributions.length > 0,
    );

    return (
        <Card>
            <CardHeader>
                <CardTitle>Item-level profit distribution</CardTitle>
                <CardDescription>
                    Frozen rule versions and immutable recipient snapshots are
                    shown independently for each transaction item.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-5">
                {transaction.status === 'distributed' && !hasRows && (
                    <div className="rounded-lg border border-emerald-500/30 bg-emerald-500/5 p-4">
                        <p className="font-medium text-emerald-700 dark:text-emerald-300">
                            Distribution completed successfully.
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            No item had positive distributable profit, so no
                            ledger rows were created.
                        </p>
                    </div>
                )}

                {transaction.items.map((item) => {
                    const profit = moneyToSignedCents(item.net_profit);
                    const isPositive = profit !== null && profit > 0n;

                    return (
                        <section
                            key={item.id}
                            className="rounded-xl border p-4"
                        >
                            <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                <div>
                                    <h3 className="font-medium">
                                        {item.product_name_snapshot}
                                    </h3>
                                    <p className="mt-1 font-mono text-xs text-muted-foreground">
                                        {item.product_sku_snapshot} ·{' '}
                                        {item.category_name_snapshot ??
                                            'No category snapshot'}
                                    </p>
                                </div>
                                <div className="sm:text-right">
                                    <p className="text-xs text-muted-foreground">
                                        Item net profit
                                    </p>
                                    <p className="font-semibold tabular-nums">
                                        {formatRupiah(item.net_profit)}
                                    </p>
                                </div>
                            </div>

                            {!isPositive ? (
                                <div className="mt-4 rounded-lg bg-muted/50 p-3 text-sm text-muted-foreground">
                                    No profit distribution required. This
                                    item&apos;s net profit is zero or negative.
                                </div>
                            ) : (
                                <>
                                    <FrozenRule
                                        item={item}
                                        isDraft={transaction.status === 'draft'}
                                    />
                                    <DistributionState
                                        transaction={transaction}
                                        item={item}
                                    />
                                </>
                            )}
                        </section>
                    );
                })}
            </CardContent>
        </Card>
    );
}

function FrozenRule({
    item,
    isDraft,
}: {
    item: TransactionDetail['items'][number];
    isDraft: boolean;
}) {
    if (!item.applied_rule) {
        if (isDraft) {
            return (
                <p className="mt-4 text-sm text-muted-foreground">
                    The applicable rule version will be resolved and frozen when
                    this draft is completed.
                </p>
            );
        }

        return (
            <p className="mt-4 text-sm text-destructive">
                A frozen rule version has not been recorded.
            </p>
        );
    }

    return (
        <div className="mt-4 flex flex-wrap items-center gap-2 text-sm">
            <Badge variant="secondary">Frozen at completion</Badge>
            <span className="font-medium">
                {item.applied_rule.scheme.name} · Version{' '}
                {item.applied_rule.version}
            </span>
            <span className="text-muted-foreground">
                Resolved {nullableDateTime(item.rule_resolved_at)}
            </span>
        </div>
    );
}

function DistributionState({
    transaction,
    item,
}: {
    transaction: TransactionDetail;
    item: TransactionDetail['items'][number];
}) {
    if (item.distributions.length === 0) {
        const message =
            transaction.status === 'draft'
                ? 'Complete the draft to freeze its applicable rule and queue distribution.'
                : transaction.status === 'processing_distribution'
                  ? 'Distribution is currently processing.'
                  : transaction.status === 'distribution_failed'
                    ? 'No ledger rows were committed. Retry the frozen transaction to continue.'
                    : transaction.status === 'distributed'
                      ? 'Distribution completed without a persisted row for this positive item.'
                      : transaction.status === 'cancelled'
                        ? 'This cancelled transaction will not be distributed.'
                        : 'Waiting for distribution.';

        return (
            <div className="mt-4 rounded-lg bg-muted/50 p-3 text-sm text-muted-foreground">
                {message}
            </div>
        );
    }

    return (
        <div className="mt-4 overflow-x-auto rounded-lg border">
            <table className="w-full min-w-[560px] text-sm">
                <thead className="border-b bg-muted/40 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    <tr>
                        <th className="px-4 py-3">Recipient snapshot</th>
                        <th className="px-4 py-3 text-right">Percentage</th>
                        <th className="px-4 py-3 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {item.distributions.map((distribution) => (
                        <tr key={distribution.id}>
                            <td className="px-4 py-3">
                                {distribution.recipient_name_snapshot}
                            </td>
                            <td className="px-4 py-3 text-right font-mono tabular-nums">
                                {distribution.percentage}%
                            </td>
                            <td className="px-4 py-3 text-right font-medium tabular-nums">
                                {formatRupiah(distribution.amount)}
                            </td>
                        </tr>
                    ))}
                </tbody>
                <tfoot className="border-t bg-muted/30">
                    <tr>
                        <td
                            colSpan={2}
                            className="px-4 py-3 text-right font-medium"
                        >
                            Exact item distribution total
                        </td>
                        <td className="px-4 py-3 text-right font-semibold tabular-nums">
                            {formatRupiah(item.distributed_total)}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

function nullableDateTime(value: string | null): string {
    if (value === null) {
        return 'time not recorded';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}
