import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import ProfitRecipientController from '@/actions/App/Http/Controllers/ProfitRecipients/ProfitRecipientController';
import ProfitRecipientForm from '@/components/profit-recipients/profit-recipient-form';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    ProfitRecipientCreateDefaults,
    ProfitRecipientInvestor,
    ProfitRecipientTypeOption,
} from '@/types';

type PageProps = {
    type_options: ProfitRecipientTypeOption[];
    investors: ProfitRecipientInvestor[];
    investor_option_limit: number;
    defaults: ProfitRecipientCreateDefaults;
};

export default function CreateProfitRecipient({
    type_options: typeOptions,
    investors,
    investor_option_limit: investorOptionLimit,
    defaults,
}: PageProps) {
    return (
        <>
            <Head title="Create Profit Recipient" />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <Button asChild variant="ghost" className="mb-2 -ml-3">
                        <Link href={ProfitRecipientController.index()}>
                            <ArrowLeft />
                            Profit Recipients
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Create Profit Recipient
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Add a recipient for profit allocations.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Recipient details</CardTitle>
                        <CardDescription>
                            The code must be unique and is case-sensitive.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ProfitRecipientForm
                            mode="create"
                            investors={investors}
                            typeOptions={typeOptions}
                            defaults={defaults}
                            investorOptionLimit={investorOptionLimit}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CreateProfitRecipient.layout = {
    breadcrumbs: [
        {
            title: 'Profit Recipients',
            href: ProfitRecipientController.index(),
        },
        {
            title: 'Create',
            href: '#',
        },
    ],
};
