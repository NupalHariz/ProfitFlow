import { CircleAlert, CircleEllipsis } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatRupiah } from '@/lib/money';
import type { MoneyPreview } from '@/lib/money';

export default function TransactionSummary({
    preview,
    title = 'Transaction preview',
}: {
    preview: MoneyPreview;
    title?: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent>
                {preview.state === 'complete' ? (
                    <div className="grid gap-4 sm:grid-cols-3">
                        <SummaryValue
                            label="Total revenue"
                            value={formatRupiah(preview.totalRevenue)}
                        />
                        <SummaryValue
                            label="Production cost"
                            value={formatRupiah(preview.totalProductionCost)}
                        />
                        <SummaryValue
                            label="Net profit"
                            value={formatRupiah(preview.netProfit)}
                            emphasis
                        />
                    </div>
                ) : (
                    <div className="flex items-start gap-3 rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                        {preview.state === 'invalid' ? (
                            <CircleAlert className="mt-0.5 size-4 shrink-0 text-red-500" />
                        ) : (
                            <CircleEllipsis className="mt-0.5 size-4 shrink-0" />
                        )}
                        <p>
                            {preview.state === 'invalid'
                                ? 'A quantity or money value is invalid or exceeds the supported range.'
                                : 'Select a product and enter a valid quantity for every row to see exact totals.'}
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function SummaryValue({
    label,
    value,
    emphasis = false,
}: {
    label: string;
    value: string;
    emphasis?: boolean;
}) {
    return (
        <div className="rounded-lg border bg-muted/20 p-4">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p
                className={
                    emphasis
                        ? 'mt-2 text-lg font-semibold'
                        : 'mt-2 text-lg font-medium'
                }
            >
                {value}
            </p>
        </div>
    );
}
