import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    Mail,
    Pencil,
    Phone,
    UserRound,
} from 'lucide-react';
import InvestorController from '@/actions/App/Http/Controllers/Investors/InvestorController';
import DeleteInvestorDialog from '@/components/investors/delete-investor-dialog';
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
import type {
    InvestorAllocationSummary,
    InvestorFormData,
    InvestorLinkedRecipient,
} from '@/types';

type PageProps = {
    investor: InvestorFormData;
    linked_recipient: InvestorLinkedRecipient | null;
    allocation_summary: InvestorAllocationSummary;
};

export default function ShowInvestor({
    investor,
    linked_recipient: linkedRecipient,
    allocation_summary: allocationSummary,
}: PageProps) {
    return (
        <>
            <Head title={investor.name} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                    <div>
                        <Button asChild variant="ghost" className="mb-2 -ml-3">
                            <Link href={InvestorController.index()}>
                                <ArrowLeft />
                                Investors
                            </Link>
                        </Button>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {investor.name}
                            </h1>
                            <InvestorStatusBadge active={investor.is_active} />
                        </div>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Investor business profile and read-only allocation
                            identity summary.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href={InvestorController.edit(investor.id)}>
                                <Pencil />
                                Edit
                            </Link>
                        </Button>
                        <InvestorStatusDialog
                            investorId={investor.id}
                            investorName={investor.name}
                            isActive={investor.is_active}
                        />
                        <DeleteInvestorDialog
                            investorId={investor.id}
                            investorName={investor.name}
                            hasLinkedRecipient={linkedRecipient !== null}
                        />
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Investor profile</CardTitle>
                        <CardDescription>
                            Profile changes never synchronize a linked Profit
                            Recipient or financial history.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                        <ProfileValue
                            icon={Mail}
                            label="Email"
                            value={investor.email ?? 'Not provided'}
                        />
                        <ProfileValue
                            icon={Phone}
                            label="Phone"
                            value={investor.phone ?? 'Not provided'}
                        />
                        <ProfileValue
                            icon={UserRound}
                            label="Investor status"
                            value={investor.is_active ? 'Active' : 'Inactive'}
                        />
                        <ProfileValue
                            icon={CalendarDays}
                            label="Record dates"
                            value={`Created ${formatDateTime(investor.created_at)}`}
                            detail={`Updated ${formatDateTime(investor.updated_at)}`}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Linked Profit Recipient</CardTitle>
                        <CardDescription>
                            Investor and Profit Recipient status are
                            independent. Recipient editing is not available in
                            this checkpoint.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {linkedRecipient === null ? (
                            <div className="rounded-lg border border-dashed p-8 text-center">
                                <p className="font-medium">Not linked</p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Creating an Investor does not automatically
                                    create a Profit Recipient.
                                </p>
                            </div>
                        ) : (
                            <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-5">
                                <SummaryValue
                                    label="Display name"
                                    value={linkedRecipient.display_name}
                                />
                                <SummaryValue
                                    label="Code"
                                    value={linkedRecipient.code}
                                    mono
                                />
                                <SummaryValue
                                    label="Type"
                                    value={capitalize(linkedRecipient.type)}
                                />
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Recipient status
                                    </p>
                                    <div className="mt-2">
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
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <SummaryValue
                                        label="Allocations"
                                        value={String(
                                            allocationSummary.allocation_count,
                                        )}
                                    />
                                    <SummaryValue
                                        label="Distributions"
                                        value={String(
                                            allocationSummary.distribution_count,
                                        )}
                                    />
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function ProfileValue({
    icon: Icon,
    label,
    value,
    detail,
}: {
    icon: typeof Mail;
    label: string;
    value: string;
    detail?: string;
}) {
    return (
        <div className="flex gap-3">
            <Icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <div className="min-w-0">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="mt-1 font-medium break-words">{value}</p>
                {detail && (
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {detail}
                    </p>
                )}
            </div>
        </div>
    );
}

function SummaryValue({
    label,
    value,
    mono = false,
}: {
    label: string;
    value: string;
    mono?: boolean;
}) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p
                className={`mt-1 font-medium break-words ${mono ? 'font-mono' : ''}`}
            >
                {value}
            </p>
        </div>
    );
}

function formatDateTime(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function capitalize(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

ShowInvestor.layout = {
    breadcrumbs: [
        {
            title: 'Investors',
            href: InvestorController.index(),
        },
    ],
};
