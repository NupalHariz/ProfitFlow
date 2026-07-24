import { Badge } from '@/components/ui/badge';
import type { ProfitRecipientType } from '@/types';

export default function ProfitRecipientTypeBadge({
    type,
}: {
    type: ProfitRecipientType;
}) {
    return (
        <Badge variant="outline">
            {type === 'investor' ? 'Investor' : 'Company'}
        </Badge>
    );
}
