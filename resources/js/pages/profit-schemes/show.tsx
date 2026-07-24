import { Head, Link } from '@inertiajs/react';
import { GitBranch, LockKeyhole, Pencil, Plus, Users } from 'lucide-react';
import ProfitSchemeController from '@/actions/App/Http/Controllers/ProfitSchemes/ProfitSchemeController';
import ProfitSchemeVersionController from '@/actions/App/Http/Controllers/ProfitSchemes/ProfitSchemeVersionController';
import PublishVersionDialog from '@/components/profit-schemes/publish-version-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatRupiah } from '@/lib/money';
import { formatPercentage } from '@/lib/percentage';

type RecipientSummary = {
    id: number;
    code: string;
    display_name: string;
    type: string;
    is_active: boolean;
    is_available_for_allocation: boolean;
};

type VersionData = {
    id: number;
    version: number;
    category: {
        id: number;
        name: string;
        is_active: boolean;
        is_deleted: boolean;
    } | null;
    minimum_profit: string | null;
    maximum_profit: string | null;
    effective_from: string;
    effective_until: string | null;
    priority: number;
    is_active: boolean;
    published_at: string | null;
    created_at: string;
    creator: {
        id: number;
        name: string;
    };
    lifecycle_status: 'draft' | 'published';
    version_status:
        'draft' | 'current' | 'scheduled' | 'superseded' | 'expired';
    is_published: boolean;
    is_historically_referenced: boolean;
    is_immutable: boolean;
    can_edit: boolean;
    allocation_total: string;
    unavailable_allocation_count: number;
    is_ready_to_publish: boolean;
    allocations: {
        id: number;
        percentage: string;
        recipient: RecipientSummary;
    }[];
};

type PageProps = {
    scheme: {
        id: number;
        name: string;
        code: string;
        description: string | null;
        is_active: boolean;
        created_at: string;
        creator: {
            id: number;
            name: string;
        };
        versions: VersionData[];
    };
};

export default function ProfitSchemeShow({ scheme }: PageProps) {
    return (
        <>
            <Head title={scheme.name} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                    <div>
                        <div className="mb-2 flex flex-wrap items-center gap-2">
                            <Badge
                                variant={
                                    scheme.is_active ? 'default' : 'secondary'
                                }
                            >
                                {scheme.is_active ? 'Active' : 'Inactive'}
                            </Badge>
                            <span className="font-mono text-sm text-muted-foreground">
                                {scheme.code}
                            </span>
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {scheme.name}
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                            {scheme.description ?? 'No description provided.'}
                        </p>
                        <p className="mt-3 text-xs text-muted-foreground">
                            Created by {scheme.creator.name} on{' '}
                            {formatDateTime(scheme.created_at)}
                        </p>
                    </div>
                    <Button asChild>
                        <Link
                            href={ProfitSchemeVersionController.create(
                                scheme.id,
                            )}
                        >
                            <Plus />
                            Create new version
                        </Link>
                    </Button>
                </div>

                <div className="flex items-center gap-2">
                    <GitBranch className="size-5 text-muted-foreground" />
                    <div>
                        <h2 className="font-semibold">Version history</h2>
                        <p className="text-sm text-muted-foreground">
                            New transactions use the latest applicable version
                            for their transaction date. Previous versions remain
                            available for transaction history. Published or used
                            versions cannot be edited.
                        </p>
                    </div>
                </div>

                {scheme.versions.length === 0 ? (
                    <Card className="border-dashed">
                        <CardHeader className="items-center py-12 text-center">
                            <CardTitle>No versions yet</CardTitle>
                            <CardDescription>
                                Create a draft rule, configure allocations, then
                                publish it.
                            </CardDescription>
                            <Button asChild className="mt-3">
                                <Link
                                    href={ProfitSchemeVersionController.create(
                                        scheme.id,
                                    )}
                                >
                                    <Plus />
                                    Create first version
                                </Link>
                            </Button>
                        </CardHeader>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {scheme.versions.map((version) => (
                            <VersionCard
                                key={version.id}
                                schemeId={scheme.id}
                                version={version}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function VersionCard({
    schemeId,
    version,
}: {
    schemeId: number;
    version: VersionData;
}) {
    return (
        <Card>
            <CardHeader>
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <CardTitle>Version {version.version}</CardTitle>
                            <Badge
                                variant={
                                    version.is_published ? 'default' : 'outline'
                                }
                            >
                                {version.is_published ? 'Published' : 'Draft'}
                            </Badge>
                            {version.is_published && (
                                <Badge
                                    variant={
                                        version.version_status === 'current'
                                            ? 'default'
                                            : 'outline'
                                    }
                                >
                                    {versionStatusLabel(version.version_status)}
                                </Badge>
                            )}
                            <Badge
                                variant={
                                    version.is_active ? 'secondary' : 'outline'
                                }
                            >
                                {version.is_active ? 'Active' : 'Inactive'}
                            </Badge>
                            {version.is_immutable && (
                                <Badge variant="secondary">
                                    <LockKeyhole />
                                    Read-only
                                </Badge>
                            )}
                        </div>
                        <CardDescription className="mt-2">
                            Created by {version.creator.name}
                            {version.published_at
                                ? ` · Published ${formatDateTime(version.published_at)}`
                                : ` · Created ${formatDateTime(version.created_at)}`}
                        </CardDescription>
                    </div>

                    {version.can_edit && (
                        <div className="flex flex-wrap gap-2">
                            <Button asChild variant="outline">
                                <Link
                                    href={ProfitSchemeVersionController.edit({
                                        scheme: schemeId,
                                        version: version.id,
                                    })}
                                >
                                    <Pencil />
                                    Edit draft
                                </Link>
                            </Button>
                            <PublishVersionDialog
                                schemeId={schemeId}
                                versionId={version.id}
                                versionNumber={version.version}
                                disabled={!version.is_ready_to_publish}
                            />
                        </div>
                    )}
                </div>
            </CardHeader>
            <CardContent className="space-y-5">
                {version.is_historically_referenced &&
                    !version.is_published && (
                        <p className="rounded-md border border-amber-600/30 bg-amber-500/10 p-3 text-sm text-amber-800 dark:text-amber-300">
                            This version has been used by a transaction and can
                            no longer be edited.
                        </p>
                    )}

                <dl className="grid gap-4 rounded-lg border bg-muted/20 p-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt className="text-xs font-medium text-muted-foreground uppercase">
                            Category
                        </dt>
                        <dd className="mt-1 font-medium">
                            {version.category?.name ?? 'Category unavailable'}
                            {version.category?.is_deleted ? ' (deleted)' : ''}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs font-medium text-muted-foreground uppercase">
                            Profit range
                        </dt>
                        <dd className="mt-1 font-medium">
                            {formatProfitRange(
                                version.minimum_profit,
                                version.maximum_profit,
                            )}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs font-medium text-muted-foreground uppercase">
                            Effective period
                        </dt>
                        <dd className="mt-1 font-medium">
                            {formatDate(version.effective_from)} –{' '}
                            {version.effective_until
                                ? formatDate(version.effective_until)
                                : 'Open-ended'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs font-medium text-muted-foreground uppercase">
                            Priority
                        </dt>
                        <dd className="mt-1 font-medium">{version.priority}</dd>
                    </div>
                </dl>

                <div>
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                            <Users className="size-4 text-muted-foreground" />
                            <h3 className="text-sm font-semibold">
                                Allocations
                            </h3>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-xs text-muted-foreground">
                                Total
                            </span>
                            <Badge
                                variant="outline"
                                className={
                                    version.allocation_total === '100.0000'
                                        ? 'border-emerald-600/30 bg-emerald-500/10 text-emerald-700'
                                        : 'border-amber-600/30 bg-amber-500/10 text-amber-700'
                                }
                            >
                                {formatPercentage(version.allocation_total)}
                            </Badge>
                        </div>
                    </div>

                    {version.allocations.length === 0 ? (
                        <p className="rounded-md border border-dashed p-5 text-center text-sm text-muted-foreground">
                            No allocations configured.
                        </p>
                    ) : (
                        <div className="overflow-hidden rounded-lg border">
                            <div className="divide-y">
                                {version.allocations.map((allocation) => (
                                    <div
                                        key={allocation.id}
                                        className="flex flex-col justify-between gap-2 px-4 py-3 sm:flex-row sm:items-center"
                                    >
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="text-sm font-medium">
                                                    {
                                                        allocation.recipient
                                                            .display_name
                                                    }
                                                </p>
                                                {!allocation.recipient
                                                    .is_available_for_allocation && (
                                                    <Badge variant="outline">
                                                        Unavailable
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="font-mono text-xs text-muted-foreground">
                                                {allocation.recipient.code} ·{' '}
                                                {allocation.recipient.type}
                                            </p>
                                            {!allocation.recipient
                                                .is_available_for_allocation && (
                                                <p className="mt-1 text-xs text-amber-700 dark:text-amber-300">
                                                    {version.can_edit
                                                        ? 'Choose a replacement before publishing.'
                                                        : 'Unavailable for new allocations.'}
                                                </p>
                                            )}
                                        </div>
                                        <p className="font-mono text-sm font-semibold">
                                            {formatPercentage(
                                                allocation.percentage,
                                            )}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {version.can_edit && !version.is_ready_to_publish && (
                    <div className="rounded-md border border-amber-600/30 bg-amber-500/10 p-3 text-sm text-amber-800 dark:text-amber-300">
                        <p className="font-medium">
                            This version is not ready to publish.
                        </p>
                        <ul className="mt-1 list-inside list-disc space-y-1">
                            {!version.is_active && (
                                <li>Activate the version before publishing.</li>
                            )}
                            {version.unavailable_allocation_count > 0 && (
                                <li>
                                    {version.unavailable_allocation_count}{' '}
                                    {version.unavailable_allocation_count === 1
                                        ? 'allocation requires'
                                        : 'allocations require'}{' '}
                                    replacement.
                                </li>
                            )}
                            {version.allocation_total !== '100.0000' && (
                                <li>Allocations must total exactly 100%.</li>
                            )}
                        </ul>
                    </div>
                )}

                {version.is_published && (
                    <p className="flex items-center gap-2 text-xs text-muted-foreground">
                        <LockKeyhole className="size-3.5" />
                        Published versions cannot be edited.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

function formatProfitRange(
    minimum: string | null,
    maximum: string | null,
): string {
    if (minimum === null && maximum === null) {
        return 'Any net profit';
    }

    return `${minimum === null ? 'No minimum' : formatRupiah(minimum)} – ${
        maximum === null ? 'No maximum' : formatRupiah(maximum)
    }`;
}

function versionStatusLabel(status: VersionData['version_status']): string {
    return {
        draft: 'Draft',
        current: 'Current',
        scheduled: 'Scheduled',
        superseded: 'Superseded',
        expired: 'Expired',
    }[status];
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

ProfitSchemeShow.layout = {
    breadcrumbs: [
        {
            title: 'Profit schemes',
            href: ProfitSchemeController.index(),
        },
    ],
};
