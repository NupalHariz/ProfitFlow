import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    Mail,
    Pencil,
    Phone,
    UserRound,
} from 'lucide-react';
import ProfitRecipientController from '@/actions/App/Http/Controllers/ProfitRecipients/ProfitRecipientController';
import ProfitRecipientStatusBadge from '@/components/profit-recipients/profit-recipient-status-badge';
import ProfitRecipientStatusDialog from '@/components/profit-recipients/profit-recipient-status-dialog';
import ProfitRecipientTypeBadge from '@/components/profit-recipients/profit-recipient-type-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatIndonesianPhoneNumber } from '@/lib/phone';
import type {
    ProfitRecipientData,
    ProfitRecipientInvestor,
    ProfitRecipientUsageSummary,
} from '@/types';

type PageProps = {
    recipient: ProfitRecipientData;
    investor: ProfitRecipientInvestor | null;
    usage_summary: ProfitRecipientUsageSummary;
};

export default function ShowProfitRecipient({
    recipient,
    investor,
    usage_summary: usageSummary,
}: PageProps) {
    return (
        <>
            <Head title={recipient.display_name} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                    <div>
                        <Button asChild variant="ghost" className="mb-2 -ml-3">
                            <Link href={ProfitRecipientController.index()}>
                                <ArrowLeft />
                                Profit Recipients
                            </Link>
                        </Button>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {recipient.display_name}
                            </h1>
                            <ProfitRecipientTypeBadge type={recipient.type} />
                            <ProfitRecipientStatusBadge
                                active={recipient.is_active}
                            />
                        </div>
                        <p className="mt-2 font-mono text-sm text-muted-foreground">
                            {recipient.code}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link
                                href={ProfitRecipientController.edit(
                                    recipient.id,
                                )}
                            >
                                <Pencil />
                                Edit
                            </Link>
                        </Button>
                        <ProfitRecipientStatusDialog
                            recipientId={recipient.id}
                            recipientName={recipient.display_name}
                            isActive={recipient.is_active}
                        />
                    </div>
                </div>

                <Alert>
                    <AlertTitle>Managed separately</AlertTitle>
                    <AlertDescription>
                        Investor and Profit Recipient details are managed
                        separately.
                    </AlertDescription>
                </Alert>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Recipient identity</CardTitle>
                            <CardDescription>
                                Type and Investor linkage cannot be reassigned
                                after creation.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-2">
                            <SummaryValue label="Code" value={recipient.code} />
                            <SummaryValue
                                label="Display name"
                                value={recipient.display_name}
                            />
                            <ProfileValue
                                icon={CalendarDays}
                                label="Created"
                                value={formatDateTime(recipient.created_at)}
                            />
                            <ProfileValue
                                icon={CalendarDays}
                                label="Updated"
                                value={formatDateTime(recipient.updated_at)}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Linked Investor</CardTitle>
                            <CardDescription>
                                {recipient.type === 'company'
                                    ? 'Not applicable to Company recipients.'
                                    : 'Investor details.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {investor === null ? (
                                <p className="text-sm text-muted-foreground">
                                    Not applicable
                                </p>
                            ) : (
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <ProfileValue
                                        icon={UserRound}
                                        label="Investor"
                                        value={investor.name}
                                        detail={
                                            investor.is_active
                                                ? 'Active Investor'
                                                : 'Inactive Investor'
                                        }
                                    />
                                    <ProfileValue
                                        icon={Mail}
                                        label="Email"
                                        value={investor.email ?? 'No email'}
                                    />
                                    <ProfileValue
                                        icon={Phone}
                                        label="Phone"
                                        value={
                                            formatIndonesianPhoneNumber(
                                                investor.phone,
                                            ) ?? 'No phone'
                                        }
                                    />
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Usage summary</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <SummaryValue
                            label="All allocations"
                            value={String(usageSummary.allocation_count)}
                        />
                        <SummaryValue
                            label="Draft allocations"
                            value={String(
                                usageSummary.draft_allocation_count ?? 0,
                            )}
                        />
                        <SummaryValue
                            label="Published allocations"
                            value={String(
                                usageSummary.published_allocation_count ?? 0,
                            )}
                        />
                        <SummaryValue
                            label="Distributions"
                            value={String(usageSummary.distribution_count)}
                        />
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

function SummaryValue({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 font-medium break-words">{value}</p>
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

ShowProfitRecipient.layout = {
    breadcrumbs: [
        {
            title: 'Profit Recipients',
            href: ProfitRecipientController.index(),
        },
    ],
};
