import { Badge } from '@/components/ui/badge';

export default function ProfitRecipientStatusBadge({
    active,
}: {
    active: boolean;
}) {
    return (
        <Badge variant={active ? 'default' : 'secondary'}>
            {active ? 'Active' : 'Inactive'}
        </Badge>
    );
}
