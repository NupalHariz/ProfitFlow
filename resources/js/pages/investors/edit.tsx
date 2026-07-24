import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import InvestorController from '@/actions/App/Http/Controllers/Investors/InvestorController';
import DeleteInvestorDialog from '@/components/investors/delete-investor-dialog';
import InvestorForm from '@/components/investors/investor-form';
import InvestorStatusBadge from '@/components/investors/investor-status-badge';
import InvestorStatusDialog from '@/components/investors/investor-status-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { InvestorFormData, InvestorLinkedRecipient } from '@/types';

type PageProps = {
    investor: InvestorFormData;
    linked_recipient: InvestorLinkedRecipient | null;
};

export default function EditInvestor({
    investor,
    linked_recipient: linkedRecipient,
}: PageProps) {
    return (
        <>
            <Head title={`Edit ${investor.name}`} />

            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <Button asChild variant="ghost" className="mb-2 -ml-3">
                        <Link href={InvestorController.show(investor.id)}>
                            <ArrowLeft />
                            {investor.name}
                        </Link>
                    </Button>
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Edit Investor
                        </h1>
                        <InvestorStatusBadge active={investor.is_active} />
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Edit only the Investor profile. Status and deletion are
                        separate lifecycle actions.
                    </p>
                </div>

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Investor details</CardTitle>
                            <CardDescription>
                                Changing the Investor name does not rename a
                                linked Profit Recipient.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <InvestorForm mode="edit" investor={investor} />
                        </CardContent>
                    </Card>

                    <div className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Linked Profit Recipient</CardTitle>
                                <CardDescription>
                                    Read-only in Stage 3C-1.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {linkedRecipient === null ? (
                                    <p className="text-sm text-muted-foreground">
                                        Not linked
                                    </p>
                                ) : (
                                    <div className="space-y-3 text-sm">
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="font-medium">
                                                {linkedRecipient.display_name}
                                            </span>
                                            <Badge
                                                variant={
                                                    linkedRecipient.is_active
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {linkedRecipient.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </Badge>
                                        </div>
                                        <p className="font-mono text-xs text-muted-foreground">
                                            {linkedRecipient.code}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Recipient status remains independent
                                            from Investor status.
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Lifecycle</CardTitle>
                                <CardDescription>
                                    These actions affect only the Investor
                                    profile.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                <InvestorStatusDialog
                                    investorId={investor.id}
                                    investorName={investor.name}
                                    isActive={investor.is_active}
                                />
                                <DeleteInvestorDialog
                                    investorId={investor.id}
                                    investorName={investor.name}
                                    hasLinkedRecipient={
                                        linkedRecipient !== null
                                    }
                                />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

EditInvestor.layout = {
    breadcrumbs: [
        {
            title: 'Investors',
            href: InvestorController.index(),
        },
        {
            title: 'Edit',
            href: '#',
        },
    ],
};
