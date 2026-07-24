import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import InvestorController from '@/actions/App/Http/Controllers/Investors/InvestorController';
import InvestorForm from '@/components/investors/investor-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export default function CreateInvestor() {
    return (
        <>
            <Head title="Create Investor" />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <Button asChild variant="ghost" className="mb-2 -ml-3">
                        <Link href={InvestorController.index()}>
                            <ArrowLeft />
                            Investors
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Create Investor
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Add an Investor profile.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Investor details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <InvestorForm mode="create" />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CreateInvestor.layout = {
    breadcrumbs: [
        {
            title: 'Investors',
            href: InvestorController.index(),
        },
        {
            title: 'Create',
            href: '#',
        },
    ],
};
