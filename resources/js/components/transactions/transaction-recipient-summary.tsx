import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatRupiah } from '@/lib/money';
import type { TransactionDetail, TransactionRecipientSummary } from '@/types';

type Props = {
    transaction: TransactionDetail;
    recipientSummary: TransactionRecipientSummary[];
    distributedTotal: string;
};

export default function TransactionRecipientSummaryCard({
    transaction,
    recipientSummary,
    distributedTotal,
}: Props) {
    if (transaction.status !== 'distributed') {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Recipient summary</CardTitle>
                <CardDescription>
                    Distributed amounts across all profitable items.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-5">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Total
                        label="Total Net Profit"
                        value={transaction.total_net_profit}
                        detail="Overall transaction result, including loss-making items."
                    />
                    <Total
                        label="Distributed Profit"
                        value={distributedTotal}
                        detail="Profit already distributed."
                    />
                </div>

                {recipientSummary.length > 0 && (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[620px] text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3">
                                        Profit Recipient
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Contributing items
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Total amount
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {recipientSummary.map((recipient) => (
                                    <tr
                                        key={`${recipient.profit_recipient_id}:${recipient.recipient_name_snapshot}`}
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {recipient.recipient_name_snapshot}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {recipient.contributing_item_count}
                                        </td>
                                        <td className="px-4 py-3 text-right font-semibold tabular-nums">
                                            {formatRupiah(
                                                recipient.total_amount,
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function Total({
    label,
    value,
    detail,
}: {
    label: string;
    value: string;
    detail: string;
}) {
    return (
        <div className="rounded-lg border p-4">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-2 text-xl font-semibold tabular-nums">
                {formatRupiah(value)}
            </p>
            <p className="mt-1 text-xs text-muted-foreground">{detail}</p>
        </div>
    );
}
