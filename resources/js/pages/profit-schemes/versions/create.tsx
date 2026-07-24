import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import ProfitSchemeController from '@/actions/App/Http/Controllers/ProfitSchemes/ProfitSchemeController';
import ProfitSchemeVersionController from '@/actions/App/Http/Controllers/ProfitSchemes/ProfitSchemeVersionController';
import VersionForm from '@/components/profit-schemes/version-form';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { ProfitSchemeCategoryLookup, ProfitSchemeSummary } from '@/types';

type PageProps = {
    scheme: ProfitSchemeSummary;
    categories: ProfitSchemeCategoryLookup[];
};

export default function CreateProfitSchemeVersion({
    scheme,
    categories,
}: PageProps) {
    const form = ProfitSchemeVersionController.store.form(scheme.id);

    return (
        <>
            <Head title={`Create version · ${scheme.name}`} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <Button asChild variant="ghost" className="mb-2 -ml-3">
                        <Link href={ProfitSchemeController.show(scheme.id)}>
                            <ArrowLeft />
                            {scheme.name}
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Create draft version
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Define the rule, then configure its allocations.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Rule configuration</CardTitle>
                        <CardDescription>
                            Creating this draft does not activate or publish it.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <VersionForm
                            action={form.action}
                            method={form.method}
                            categories={categories}
                            submitLabel="Create draft version"
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CreateProfitSchemeVersion.layout = {
    breadcrumbs: [
        {
            title: 'Profit schemes',
            href: ProfitSchemeController.index(),
        },
    ],
};
