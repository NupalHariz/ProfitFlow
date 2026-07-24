import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatRupiah, moneyToSignedCents } from '@/lib/money';
import { formatPercentage } from '@/lib/percentage';
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
                    Review the applied rule and recipient amounts for each item.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-5">
                {transaction.status === 'distributed' && !hasRows && (
                    <div className="rounded-lg border border-emerald-500/30 bg-emerald-500/5 p-4">
                        <p className="font-medium text-emerald-700 dark:text-emerald-300">
                            Distribution completed successfully.
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            No item had positive profit, so no distribution was
                            required.
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
                                            'No category'}
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
                    The applied rule is selected when this draft is completed.
                </p>
            );
        }

        return (
            <p className="mt-4 text-sm text-destructive">
                No applied rule was recorded.
            </p>
        );
    }

    return (
        <div className="mt-4 flex flex-wrap items-center gap-2 text-sm">
            <Badge variant="secondary">Applied at completion</Badge>
            <span className="font-medium">
                {item.applied_rule.scheme.name} · Version{' '}
                {item.applied_rule.version}
            </span>
            <span className="text-muted-foreground">
                Applied {nullableDateTime(item.rule_resolved_at)}
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
                ? 'Complete the draft to select its rule and start distribution.'
                : transaction.status === 'processing_distribution'
                  ? 'Distribution is currently processing.'
                  : transaction.status === 'distribution_failed'
                    ? 'Distribution failed before an amount was recorded. Retry to continue.'
                    : transaction.status === 'distributed'
                      ? 'No distribution amount was recorded for this item.'
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
                        <th className="px-4 py-3">Profit Recipient</th>
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
                                {formatPercentage(distribution.percentage)}
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
                            Item distribution total
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
