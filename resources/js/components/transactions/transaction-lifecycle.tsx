import {
    AlertCircle,
    CircleCheckBig,
    Clock3,
    LoaderCircle,
} from 'lucide-react';
import TransactionRetryDialog from '@/components/transactions/transaction-retry-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { TransactionDetail, TransactionStatus } from '@/types';

const statusContent: Record<
    TransactionStatus,
    { title: string; description: string }
> = {
    draft: {
        title: 'Draft transaction',
        description:
            'The transaction remains editable until an administrator confirms completion.',
    },
    pending_distribution: {
        title: 'Waiting for profit distribution',
        description: 'The completed transaction is waiting for distribution.',
    },
    processing_distribution: {
        title: 'Profit distribution is processing',
        description: 'Distribution is in progress.',
    },
    distributed: {
        title: 'Distribution completed',
        description: 'Profit has been distributed.',
    },
    distribution_failed: {
        title: 'Distribution needs attention',
        description:
            'Distribution failed. Retry uses the same completed transaction and applied rules.',
    },
    cancelled: {
        title: 'Transaction cancelled',
        description:
            'This transaction was cancelled and can no longer be edited or completed.',
    },
};

export default function TransactionLifecycle({
    transaction,
}: {
    transaction: TransactionDetail;
}) {
    const content = statusContent[transaction.status];
    const isProcessing =
        transaction.status === 'pending_distribution' ||
        transaction.status === 'processing_distribution';
    const isFailed = transaction.status === 'distribution_failed';
    const Icon = isFailed
        ? AlertCircle
        : isProcessing
          ? LoaderCircle
          : transaction.status === 'distributed'
            ? CircleCheckBig
            : Clock3;

    return (
        <Card>
            <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle>Transaction status</CardTitle>
                    <CardDescription>
                        {transaction.status === 'cancelled'
                            ? 'Review the cancellation status and recorded time.'
                            : 'Review completion and distribution progress.'}
                    </CardDescription>
                </div>
                {transaction.can_retry_distribution && (
                    <TransactionRetryDialog
                        transactionId={transaction.id}
                        mode="retry"
                    />
                )}
                {transaction.can_redrive_distribution && (
                    <TransactionRetryDialog
                        transactionId={transaction.id}
                        mode="redrive"
                    />
                )}
            </CardHeader>
            <CardContent className="space-y-5">
                <Alert variant={isFailed ? 'destructive' : 'default'}>
                    <Icon
                        className={
                            transaction.status === 'processing_distribution'
                                ? 'animate-spin'
                                : undefined
                        }
                    />
                    <AlertTitle>{content.title}</AlertTitle>
                    <AlertDescription>{content.description}</AlertDescription>
                </Alert>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
                </div>

                {transaction.completer && (
                    <div>
                        <p className="text-xs text-muted-foreground">
                            Completed by
                        </p>
                        <p className="mt-1 text-sm font-medium">
                            {transaction.completer.name}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {transaction.completer.email}
                        </p>
                    </div>
                )}

                {transaction.failure_reason && (
                    <div className="rounded-lg border border-destructive/30 bg-destructive/5 p-4">
                        <p className="text-xs font-medium text-destructive">
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

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}
