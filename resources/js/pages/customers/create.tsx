import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import CustomerController from '@/actions/App/Http/Controllers/Customers/CustomerController';
import CustomerForm from '@/components/customers/customer-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export default function CreateCustomer() {
    return (
        <>
            <Head title="Create Customer" />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <Button asChild variant="ghost" className="mb-2 -ml-3">
                        <Link href={CustomerController.index()}>
                            <ArrowLeft />
                            Customers
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Create Customer
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Add a Customer for sales transactions.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Customer details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <CustomerForm mode="create" />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CreateCustomer.layout = {
    breadcrumbs: [
        {
            title: 'Customers',
            href: CustomerController.index(),
        },
        {
            title: 'Create',
            href: '#',
        },
    ],
};
