import { Badge } from '@/components/ui/badge';

export default function InvestorStatusBadge({ active }: { active: boolean }) {
    return (
        <Badge variant={active ? 'default' : 'secondary'}>
            {active ? 'Active' : 'Inactive'}
        </Badge>
    );
}
