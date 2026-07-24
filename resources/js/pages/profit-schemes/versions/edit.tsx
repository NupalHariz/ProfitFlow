import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, LockKeyhole } from 'lucide-react';
import ProfitSchemeAllocationController from '@/actions/App/Http/Controllers/ProfitSchemes/ProfitSchemeAllocationController';
import ProfitSchemeController from '@/actions/App/Http/Controllers/ProfitSchemes/ProfitSchemeController';
import ProfitSchemeVersionController from '@/actions/App/Http/Controllers/ProfitSchemes/ProfitSchemeVersionController';
import AllocationEditor from '@/components/profit-schemes/allocation-editor';
import PublishVersionDialog from '@/components/profit-schemes/publish-version-dialog';
import VersionForm from '@/components/profit-schemes/version-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatPercentage } from '@/lib/percentage';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    ProfitRecipientLookup,
    ProfitSchemeCategoryLookup,
    ProfitSchemeSummary,
    ProfitSchemeVersionFormData,
} from '@/types';

type PageProps = {
    scheme: ProfitSchemeSummary;
    version: ProfitSchemeVersionFormData;
    categories: ProfitSchemeCategoryLookup[];
    recipients: ProfitRecipientLookup[];
    allocation_recipients: ProfitRecipientLookup[];
    lookup_limit: number;
};

export default function EditProfitSchemeVersion({
    scheme,
    version,
    categories,
    recipients,
    allocation_recipients,
}: PageProps) {
    const ruleForm = ProfitSchemeVersionController.update.form({
        scheme: scheme.id,
        version: version.id,
    });
    const allocationForm = ProfitSchemeAllocationController.update.form({
        scheme: scheme.id,
        version: version.id,
    });

    return (
        <>
            <Head title={`Version ${version.version} · ${scheme.name}`} />

            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <Button asChild variant="ghost" className="mb-2 -ml-3">
                            <Link href={ProfitSchemeController.show(scheme.id)}>
                                <ArrowLeft />
                                {scheme.name}
                            </Link>
                        </Button>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Version {version.version}
                            </h1>
                            <Badge
                                variant={
                                    version.is_published ? 'default' : 'outline'
                                }
                            >
                                {version.is_published ? 'Published' : 'Draft'}
                            </Badge>
                            {version.is_immutable && (
                                <Badge variant="secondary">
                                    <LockKeyhole />
                                    Read-only
                                </Badge>
                            )}
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {version.can_edit
                                ? 'Update the rule and its allocations.'
                                : 'This version can no longer be edited.'}
                        </p>
                    </div>

                    {version.can_edit && (
                        <PublishVersionDialog
                            schemeId={scheme.id}
                            versionId={version.id}
                            versionNumber={version.version}
                            disabled={!version.is_ready_to_publish}
                        />
                    )}
                </div>

                {version.is_historically_referenced &&
                    !version.is_published && (
                        <p className="rounded-md border border-amber-600/30 bg-amber-500/10 p-4 text-sm text-amber-800 dark:text-amber-300">
                            This version has been used by a transaction and can
                            no longer be edited.
                        </p>
                    )}

                <Card>
                    <CardHeader>
                        <CardTitle>Rule configuration</CardTitle>
                        <CardDescription>
                            Category, profit range, effective period, priority,
                            and version activity.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <VersionForm
                            action={ruleForm.action}
                            method={ruleForm.method}
                            categories={categories}
                            version={version}
                            submitLabel="Save rule configuration"
                            disabled={!version.can_edit}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Allocation editor</CardTitle>
                        <CardDescription>
                            Define how profit will be shared between recipients.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {version.can_edit ? (
                            <AllocationEditor
                                action={allocationForm.action}
                                method={allocationForm.method}
                                allocations={version.allocations}
                                recipients={recipients}
                                allocationRecipients={allocation_recipients}
                            />
                        ) : (
                            <ReadOnlyAllocations
                                allocations={version.allocations}
                                recipients={allocation_recipients}
                                total={version.allocation_total}
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function ReadOnlyAllocations({
    allocations,
    recipients,
    total,
}: {
    allocations: ProfitSchemeVersionFormData['allocations'];
    recipients: ProfitRecipientLookup[];
    total: string;
}) {
    if (allocations.length === 0) {
        return (
            <p className="rounded-md border border-dashed p-5 text-center text-sm text-muted-foreground">
                No allocations configured.
            </p>
        );
    }

    return (
        <div className="overflow-hidden rounded-lg border">
            <div className="divide-y">
                {allocations.map((allocation) => {
                    const recipient = recipients.find(
                        (candidate) =>
                            candidate.id === allocation.profit_recipient_id,
                    );

                    return (
                        <div
                            key={allocation.id}
                            className="flex items-center justify-between gap-3 px-4 py-3"
                        >
                            <div>
                                <p className="text-sm font-medium">
                                    {recipient?.display_name ??
                                        `Recipient ${allocation.profit_recipient_id}`}
                                </p>
                                <p className="font-mono text-xs text-muted-foreground">
                                    {recipient?.code ?? 'Unavailable'}
                                    {recipient && !recipient.is_available
                                        ? ' · unavailable for new draft allocations'
                                        : ''}
                                </p>
                                {recipient?.investor && (
                                    <p className="text-xs text-muted-foreground">
                                        Linked Investor:{' '}
                                        {recipient.investor.name}
                                    </p>
                                )}
                            </div>
                            <p className="font-mono text-sm font-semibold">
                                {formatPercentage(allocation.percentage)}
                            </p>
                        </div>
                    );
                })}
            </div>
            <div className="flex justify-between border-t bg-muted/30 px-4 py-3 text-sm font-semibold">
                <span>Total</span>
                <span className="font-mono">{formatPercentage(total)}</span>
            </div>
        </div>
    );
}

EditProfitSchemeVersion.layout = {
    breadcrumbs: [
        {
            title: 'Profit schemes',
            href: ProfitSchemeController.index(),
        },
    ],
};
