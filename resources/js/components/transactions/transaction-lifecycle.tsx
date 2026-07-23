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
        title: 'Waiting for the critical queue',
        description:
            'Completion is final and the frozen transaction is waiting for an asynchronous distribution worker.',
    },
    processing_distribution: {
        title: 'Profit distribution is processing',
        description:
            'A worker is calculating and atomically persisting distributions from the frozen rule versions.',
    },
    distributed: {
        title: 'Distribution completed',
        description:
            'The immutable item-level distribution ledger is complete.',
    },
    distribution_failed: {
        title: 'Distribution needs attention',
        description:
            'The final queue attempt failed. Retrying will use the same financial snapshots and frozen rule versions.',
    },
    cancelled: {
        title: 'Transaction cancelled',
        description:
            'This transaction is read-only. Cancellation actions are not available in this workflow.',
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
                    <CardTitle>Transaction lifecycle</CardTitle>
                    <CardDescription>
                        Completion and distribution timestamps are recorded by
                        the authoritative backend workflow.
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
