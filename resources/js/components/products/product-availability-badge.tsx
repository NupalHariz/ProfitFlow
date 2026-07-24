import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export default function ProductAvailabilityBadge({
    productActive,
    categoryActive,
}: {
    productActive: boolean;
    categoryActive: boolean;
}) {
    const available = productActive && categoryActive;

    return (
        <Badge
            variant="outline"
            aria-label={`Transaction availability: ${
                available ? 'Available' : 'Unavailable'
            }`}
            className={cn(
                available
                    ? 'border-emerald-600/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                    : 'border-amber-600/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
            )}
        >
            {available ? 'Available' : 'Unavailable'}
        </Badge>
    );
}
