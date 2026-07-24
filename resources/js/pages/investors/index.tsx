import { Form, Head, Link } from '@inertiajs/react';
import { Eye, Pencil, Plus, Search } from 'lucide-react';
import { useState } from 'react';
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
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    InvestorFilters,
    InvestorPagination,
    InvestorSummary,
} from '@/types';

type PageProps = {
    filters: InvestorFilters;
    investors: InvestorPagination;
};

export default function InvestorIndex({ filters, investors }: PageProps) {
    const [status, setStatus] = useState(filters.status);

    return (
        <>
            <Head title="Investors" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Investors
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Maintain Investor profiles independently from Profit
                            Recipient allocation identities.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={InvestorController.create()}>
                            <Plus />
                            Create Investor
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardContent>
                        <Form
                            {...InvestorController.index.form()}
                            className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem_auto_auto]"
                        >
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                <Input
                                    name="search"
                                    defaultValue={filters.search}
                                    placeholder="Search name, email, or phone"
                                    className="pl-9"
                                />
                            </div>
                            <input type="hidden" name="status" value={status} />
                            <Select
                                value={status}
                                onValueChange={(value) =>
                                    setStatus(
                                        value as InvestorFilters['status'],
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
                                filters.status !== 'all') && (
                                <Button variant="ghost" asChild>
                                    <Link href={InvestorController.index()}>
                                        Clear
                                    </Link>
                                </Button>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {investors.data.length === 0 ? (
                    <Card className="border-dashed">
                        <CardHeader className="items-center py-12 text-center">
                            <CardTitle>No Investors found</CardTitle>
                            <CardDescription>
                                Adjust the filters or create the first Investor.
                            </CardDescription>
                            <Button asChild className="mt-3">
                                <Link href={InvestorController.create()}>
                                    <Plus />
                                    Create Investor
                                </Link>
                            </Button>
                        </CardHeader>
                    </Card>
                ) : (
                    <>
                        <div className="grid gap-3 lg:hidden">
                            {investors.data.map((investor) => (
                                <Card key={investor.id}>
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <CardTitle>
                                                    {investor.name}
                                                </CardTitle>
                                                <CardDescription className="mt-1">
                                                    {contactSummary(investor)}
                                                </CardDescription>
                                            </div>
                                            <InvestorStatusBadge
                                                active={investor.is_active}
                                            />
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <RecipientSummary investor={investor} />
                                        <div className="flex flex-wrap gap-2">
                                            <InvestorActions
                                                investor={investor}
                                            />
                                        </div>
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
                                                Investor
                                            </th>
                                            <th className="px-5 py-3">
                                                Contact
                                            </th>
                                            <th className="px-5 py-3">
                                                Investor Status
                                            </th>
                                            <th className="px-5 py-3">
                                                Profit Recipient
                                            </th>
                                            <th className="px-5 py-3">
                                                Updated
                                            </th>
                                            <th className="px-5 py-3">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {investors.data.map((investor) => (
                                            <tr
                                                key={investor.id}
                                                className="hover:bg-muted/30"
                                            >
                                                <td className="px-5 py-4">
                                                    <p className="font-medium">
                                                        {investor.name}
                                                    </p>
                                                </td>
                                                <td className="px-5 py-4">
                                                    <p>
                                                        {investor.email ??
                                                            'No email'}
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {investor.phone ??
                                                            'No phone'}
                                                    </p>
                                                </td>
                                                <td className="px-5 py-4">
                                                    <InvestorStatusBadge
                                                        active={
                                                            investor.is_active
                                                        }
                                                    />
                                                </td>
                                                <td className="px-5 py-4">
                                                    <RecipientSummary
                                                        investor={investor}
                                                    />
                                                </td>
                                                <td className="px-5 py-4 whitespace-nowrap text-muted-foreground">
                                                    {formatDate(
                                                        investor.updated_at,
                                                    )}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <div className="flex flex-wrap gap-2">
                                                        <InvestorActions
                                                            investor={investor}
                                                        />
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Card>

                        <Pagination investors={investors} />
                    </>
                )}
            </div>
        </>
    );
}

function InvestorActions({ investor }: { investor: InvestorSummary }) {
    return (
        <>
            <Button asChild variant="outline" size="sm">
                <Link href={InvestorController.show(investor.id)}>
                    <Eye />
                    View
                </Link>
            </Button>
            <Button asChild variant="outline" size="sm">
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
                hasLinkedRecipient={investor.linked_recipient !== null}
            />
        </>
    );
}

function RecipientSummary({ investor }: { investor: InvestorSummary }) {
    const recipient = investor.linked_recipient;

    if (recipient === null) {
        return <span className="text-muted-foreground">Not linked</span>;
    }

    return (
        <div>
            <div className="flex flex-wrap items-center gap-2">
                <span className="font-medium">{recipient.display_name}</span>
                <Badge variant={recipient.is_active ? 'default' : 'secondary'}>
                    {recipient.is_active ? 'Active' : 'Inactive'}
                </Badge>
            </div>
            <p className="mt-1 font-mono text-xs text-muted-foreground">
                {recipient.code}
            </p>
        </div>
    );
}

function Pagination({ investors }: { investors: InvestorPagination }) {
    if (investors.meta.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex flex-col justify-between gap-3 text-sm sm:flex-row sm:items-center">
            <p className="text-muted-foreground">
                Showing {investors.meta.from ?? 0}–{investors.meta.to ?? 0} of{' '}
                {investors.meta.total}
            </p>
            <div className="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={investors.links.previous === null}
                    asChild={investors.links.previous !== null}
                >
                    {investors.links.previous ? (
                        <Link href={investors.links.previous}>Previous</Link>
                    ) : (
                        <span>Previous</span>
                    )}
                </Button>
                {investors.links.pages.map((link) => (
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
                    disabled={investors.links.next === null}
                    asChild={investors.links.next !== null}
                >
                    {investors.links.next ? (
                        <Link href={investors.links.next}>Next</Link>
                    ) : (
                        <span>Next</span>
                    )}
                </Button>
            </div>
        </div>
    );
}

function contactSummary(investor: InvestorSummary): string {
    return investor.email ?? investor.phone ?? 'No contact details';
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

InvestorIndex.layout = {
    breadcrumbs: [
        {
            title: 'Investors',
            href: InvestorController.index(),
        },
    ],
};
