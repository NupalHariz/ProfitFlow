import { Form, Head, Link } from '@inertiajs/react';
import { Eye, Pencil, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import ProfitRecipientController from '@/actions/App/Http/Controllers/ProfitRecipients/ProfitRecipientController';
import ProfitRecipientStatusBadge from '@/components/profit-recipients/profit-recipient-status-badge';
import ProfitRecipientStatusDialog from '@/components/profit-recipients/profit-recipient-status-dialog';
import ProfitRecipientTypeBadge from '@/components/profit-recipients/profit-recipient-type-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    ProfitRecipientFilters,
    ProfitRecipientPagination,
    ProfitRecipientSummary,
} from '@/types';

type PageProps = {
    filters: ProfitRecipientFilters;
    recipients: ProfitRecipientPagination;
};

export default function ProfitRecipientIndex({
    filters,
    recipients,
}: PageProps) {
    const [type, setType] = useState(filters.type);
    const [status, setStatus] = useState(filters.status);

    return (
        <>
            <Head title="Profit Recipients" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Profit Recipients
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Manage the allocation identities referenced by
                            profit schemes and distributions.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={ProfitRecipientController.create()}>
                            <Plus />
                            Create Profit Recipient
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardContent>
                        <Form
                            {...ProfitRecipientController.index.form()}
                            className="grid gap-3 lg:grid-cols-[minmax(0,1fr)_11rem_11rem_auto_auto]"
                        >
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                <Input
                                    name="search"
                                    defaultValue={filters.search}
                                    placeholder="Search code, name, or Investor"
                                    className="pl-9"
                                />
                            </div>
                            <input type="hidden" name="type" value={type} />
                            <Select
                                value={type}
                                onValueChange={(value) =>
                                    setType(
                                        value as ProfitRecipientFilters['type'],
                                    )
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All types
                                    </SelectItem>
                                    <SelectItem value="investor">
                                        Investor
                                    </SelectItem>
                                    <SelectItem value="company">
                                        Company
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <input type="hidden" name="status" value={status} />
                            <Select
                                value={status}
                                onValueChange={(value) =>
                                    setStatus(
                                        value as ProfitRecipientFilters['status'],
                                    )
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All statuses
                                    </SelectItem>
                                    <SelectItem value="active">
                                        Active
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        Inactive
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Button>Apply filters</Button>
                            {(filters.search !== '' ||
                                filters.type !== 'all' ||
                                filters.status !== 'all') && (
                                <Button variant="ghost" asChild>
                                    <Link
                                        href={ProfitRecipientController.index()}
                                    >
                                        Clear
                                    </Link>
                                </Button>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {recipients.data.length === 0 ? (
                    <Card className="border-dashed">
                        <CardHeader className="items-center py-12 text-center">
                            <CardTitle>No Profit Recipients found</CardTitle>
                            <CardDescription>
                                Adjust the filters or create the first
                                allocation identity.
                            </CardDescription>
                            <Button asChild className="mt-3">
                                <Link href={ProfitRecipientController.create()}>
                                    <Plus />
                                    Create Profit Recipient
                                </Link>
                            </Button>
                        </CardHeader>
                    </Card>
                ) : (
                    <>
                        <div className="grid gap-3 lg:hidden">
                            {recipients.data.map((recipient) => (
                                <Card key={recipient.id}>
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <CardTitle>
                                                    {recipient.display_name}
                                                </CardTitle>
                                                <CardDescription className="mt-1 font-mono">
                                                    {recipient.code}
                                                </CardDescription>
                                            </div>
                                            <ProfitRecipientStatusBadge
                                                active={recipient.is_active}
                                            />
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <ProfitRecipientTypeBadge
                                                type={recipient.type}
                                            />
                                            <span className="text-sm text-muted-foreground">
                                                {linkedInvestor(recipient)}
                                            </span>
                                        </div>
                                        <Usage recipient={recipient} />
                                        <RecipientActions
                                            recipient={recipient}
                                        />
                                    </CardContent>
                                </Card>
                            ))}
                        </div>

                        <Card className="hidden overflow-hidden py-0 lg:block">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b bg-muted/40 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-5 py-3">
                                                Recipient
                                            </th>
                                            <th className="px-5 py-3">Type</th>
                                            <th className="px-5 py-3">
                                                Linked Investor
                                            </th>
                                            <th className="px-5 py-3">
                                                Status
                                            </th>
                                            <th className="px-5 py-3">Usage</th>
                                            <th className="px-5 py-3">
                                                Updated
                                            </th>
                                            <th className="px-5 py-3">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {recipients.data.map((recipient) => (
                                            <tr
                                                key={recipient.id}
                                                className="hover:bg-muted/30"
                                            >
                                                <td className="px-5 py-4">
                                                    <p className="font-medium">
                                                        {recipient.display_name}
                                                    </p>
                                                    <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                        {recipient.code}
                                                    </p>
                                                </td>
                                                <td className="px-5 py-4">
                                                    <ProfitRecipientTypeBadge
                                                        type={recipient.type}
                                                    />
                                                </td>
                                                <td className="px-5 py-4">
                                                    {recipient.investor ? (
                                                        <div>
                                                            <p className="font-medium">
                                                                {
                                                                    recipient
                                                                        .investor
                                                                        .name
                                                                }
                                                            </p>
                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                {recipient
                                                                    .investor
                                                                    .is_active
                                                                    ? 'Active Investor'
                                                                    : 'Inactive Investor'}
                                                            </p>
                                                        </div>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            Not applicable
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <ProfitRecipientStatusBadge
                                                        active={
                                                            recipient.is_active
                                                        }
                                                    />
                                                </td>
                                                <td className="px-5 py-4">
                                                    <Usage
                                                        recipient={recipient}
                                                    />
                                                </td>
                                                <td className="px-5 py-4 whitespace-nowrap text-muted-foreground">
                                                    {formatDate(
                                                        recipient.updated_at,
                                                    )}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <RecipientActions
                                                        recipient={recipient}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Card>

                        <Pagination recipients={recipients} />
                    </>
                )}
            </div>
        </>
    );
}

function RecipientActions({
    recipient,
}: {
    recipient: ProfitRecipientSummary;
}) {
    return (
        <div className="flex flex-wrap gap-2">
            <Button asChild variant="outline" size="sm">
                <Link href={ProfitRecipientController.show(recipient.id)}>
                    <Eye />
                    View
                </Link>
            </Button>
            <Button asChild variant="outline" size="sm">
                <Link href={ProfitRecipientController.edit(recipient.id)}>
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
    );
}

function Usage({ recipient }: { recipient: ProfitRecipientSummary }) {
    return (
        <p className="text-xs text-muted-foreground">
            {recipient.usage_summary.allocation_count} allocations ·{' '}
            {recipient.usage_summary.distribution_count} distributions
        </p>
    );
}

function Pagination({ recipients }: { recipients: ProfitRecipientPagination }) {
    if (recipients.meta.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex flex-col justify-between gap-3 text-sm sm:flex-row sm:items-center">
            <p className="text-muted-foreground">
                Showing {recipients.meta.from ?? 0}–{recipients.meta.to ?? 0} of{' '}
                {recipients.meta.total}
            </p>
            <div className="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={recipients.links.previous === null}
                    asChild={recipients.links.previous !== null}
                >
                    {recipients.links.previous ? (
                        <Link href={recipients.links.previous}>Previous</Link>
                    ) : (
                        <span>Previous</span>
                    )}
                </Button>
                {recipients.links.pages.map((link) => (
                    <Button
                        key={link.page}
                        variant={link.active ? 'default' : 'outline'}
                        size="sm"
                        asChild
                    >
                        <Link href={link.url}>{link.page}</Link>
                    </Button>
                ))}
                <Button
                    variant="outline"
                    size="sm"
                    disabled={recipients.links.next === null}
                    asChild={recipients.links.next !== null}
                >
                    {recipients.links.next ? (
                        <Link href={recipients.links.next}>Next</Link>
                    ) : (
                        <span>Next</span>
                    )}
                </Button>
            </div>
        </div>
    );
}

function linkedInvestor(recipient: ProfitRecipientSummary): string {
    if (recipient.investor === null) {
        return 'Not applicable';
    }

    return `${recipient.investor.name} · ${
        recipient.investor.is_active ? 'Active' : 'Inactive'
    } Investor`;
}

function formatDate(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

ProfitRecipientIndex.layout = {
    breadcrumbs: [
        {
            title: 'Profit Recipients',
            href: ProfitRecipientController.index(),
        },
    ],
};
