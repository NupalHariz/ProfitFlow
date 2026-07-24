import { Form, Head, Link } from '@inertiajs/react';
import { ArrowRight, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import ProfitSchemeController from '@/actions/App/Http/Controllers/ProfitSchemes/ProfitSchemeController';
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

type SchemeRow = {
    id: number;
    name: string;
    code: string;
    description_summary: string | null;
    is_active: boolean;
    version_count: number;
    maximum_version_number: number | null;
    created_at: string;
};

type PageProps = {
    filters: {
        search: string;
        status: 'all' | 'active' | 'inactive';
    };
    schemes: {
        data: SchemeRow[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
            from: number | null;
            to: number | null;
        };
        links: {
            previous: string | null;
            next: string | null;
            pages: {
                page: number;
                url: string;
                active: boolean;
            }[];
        };
    };
};

export default function ProfitSchemeIndex({ filters, schemes }: PageProps) {
    const [status, setStatus] = useState(filters.status);

    return (
        <>
            <Head title="Profit schemes" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Profit schemes
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Manage versioned profit-sharing rules.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={ProfitSchemeController.create()}>
                            <Plus />
                            Create profit scheme
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardContent>
                        <Form
                            {...ProfitSchemeController.index.form()}
                            className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem_auto_auto]"
                        >
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                <Input
                                    name="search"
                                    defaultValue={filters.search}
                                    placeholder="Search by name or code"
                                    aria-label="Search profit schemes"
                                    className="pl-9"
                                />
                            </div>
                            <input type="hidden" name="status" value={status} />
                            <Select
                                value={status}
                                onValueChange={(value) =>
                                    setStatus(
                                        value as PageProps['filters']['status'],
                                    )
                                }
                            >
                                <SelectTrigger
                                    className="w-full"
                                    aria-label="Filter profit schemes by status"
                                >
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
                                    <Link href={ProfitSchemeController.index()}>
                                        Clear
                                    </Link>
                                </Button>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {schemes.data.length === 0 ? (
                    <Card className="border-dashed">
                        <CardHeader className="items-center py-12 text-center">
                            <CardTitle>No profit schemes found</CardTitle>
                            <CardDescription>
                                Adjust the filters or create the first scheme.
                            </CardDescription>
                            <Button asChild className="mt-3">
                                <Link href={ProfitSchemeController.create()}>
                                    <Plus />
                                    Create profit scheme
                                </Link>
                            </Button>
                        </CardHeader>
                    </Card>
                ) : (
                    <>
                        <div className="grid gap-3 md:hidden">
                            {schemes.data.map((scheme) => (
                                <Card key={scheme.id}>
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <CardTitle>
                                                    {scheme.name}
                                                </CardTitle>
                                                <CardDescription className="mt-1 font-mono">
                                                    {scheme.code}
                                                </CardDescription>
                                            </div>
                                            <StatusBadge
                                                active={scheme.is_active}
                                            />
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <p className="text-sm text-muted-foreground">
                                            {scheme.description_summary ??
                                                'No description provided.'}
                                        </p>
                                        <div className="flex items-center justify-between text-sm">
                                            <span>
                                                {scheme.version_count}{' '}
                                                {scheme.version_count === 1
                                                    ? 'version'
                                                    : 'versions'}
                                            </span>
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <Link
                                                    href={ProfitSchemeController.show(
                                                        scheme.id,
                                                    )}
                                                >
                                                    View
                                                    <ArrowRight />
                                                </Link>
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>

                        <Card className="hidden overflow-hidden py-0 md:block">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b bg-muted/40 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-5 py-3">
                                                Scheme
                                            </th>
                                            <th className="px-5 py-3">
                                                Status
                                            </th>
                                            <th className="px-5 py-3">
                                                Versions
                                            </th>
                                            <th className="px-5 py-3">
                                                Created
                                            </th>
                                            <th className="px-5 py-3 text-right">
                                                Action
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {schemes.data.map((scheme) => (
                                            <tr
                                                key={scheme.id}
                                                className="hover:bg-muted/30"
                                            >
                                                <td className="max-w-lg px-5 py-4">
                                                    <p className="font-medium">
                                                        {scheme.name}
                                                    </p>
                                                    <p className="font-mono text-xs text-muted-foreground">
                                                        {scheme.code}
                                                    </p>
                                                    {scheme.description_summary && (
                                                        <p className="mt-1 truncate text-xs text-muted-foreground">
                                                            {
                                                                scheme.description_summary
                                                            }
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <StatusBadge
                                                        active={
                                                            scheme.is_active
                                                        }
                                                    />
                                                </td>
                                                <td className="px-5 py-4">
                                                    <p>
                                                        {scheme.version_count}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Latest:{' '}
                                                        {scheme.maximum_version_number ===
                                                        null
                                                            ? '—'
                                                            : `v${scheme.maximum_version_number}`}
                                                    </p>
                                                </td>
                                                <td className="px-5 py-4 text-muted-foreground">
                                                    {formatDate(
                                                        scheme.created_at,
                                                    )}
                                                </td>
                                                <td className="px-5 py-4 text-right">
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="sm"
                                                    >
                                                        <Link
                                                            href={ProfitSchemeController.show(
                                                                scheme.id,
                                                            )}
                                                        >
                                                            View
                                                            <ArrowRight />
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Card>
                    </>
                )}

                {schemes.meta.last_page > 1 && (
                    <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
                        <p className="text-sm text-muted-foreground">
                            Showing {schemes.meta.from}–{schemes.meta.to} of{' '}
                            {schemes.meta.total}
                        </p>
                        <div className="flex items-center gap-1">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={schemes.links.previous === null}
                                asChild={schemes.links.previous !== null}
                            >
                                {schemes.links.previous ? (
                                    <Link href={schemes.links.previous}>
                                        Previous
                                    </Link>
                                ) : (
                                    <span>Previous</span>
                                )}
                            </Button>
                            {schemes.links.pages.map((link) => (
                                <Button
                                    key={link.page}
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    asChild
                                >
                                    <Link href={link.url}>{link.page}</Link>
                                </Button>
                            ))}
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={schemes.links.next === null}
                                asChild={schemes.links.next !== null}
                            >
                                {schemes.links.next ? (
                                    <Link href={schemes.links.next}>Next</Link>
                                ) : (
                                    <span>Next</span>
                                )}
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

function StatusBadge({ active }: { active: boolean }) {
    return (
        <Badge variant={active ? 'default' : 'secondary'}>
            {active ? 'Active' : 'Inactive'}
        </Badge>
    );
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

ProfitSchemeIndex.layout = {
    breadcrumbs: [
        {
            title: 'Profit schemes',
            href: ProfitSchemeController.index(),
        },
    ],
};
