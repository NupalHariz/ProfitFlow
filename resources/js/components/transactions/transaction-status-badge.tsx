import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { TransactionStatus } from '@/types';

const labels: Record<TransactionStatus, string> = {
    draft: 'Draft',
    pending_distribution: 'Pending distribution',
    processing_distribution: 'Processing',
    distributed: 'Distributed',
    distribution_failed: 'Distribution failed',
    cancelled: 'Cancelled',
};

const styles: Record<TransactionStatus, string> = {
    draft: 'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300',
    pending_distribution:
        'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    processing_distribution:
        'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300',
    distributed:
        'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    distribution_failed:
        'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
    cancelled:
        'border-zinc-500/30 bg-zinc-500/10 text-zinc-700 dark:text-zinc-300',
};

export default function TransactionStatusBadge({
    status,
}: {
    status: TransactionStatus;
}) {
    return (
        <Badge variant="outline" className={cn(styles[status])}>
            {labels[status]}
        </Badge>
    );
}
