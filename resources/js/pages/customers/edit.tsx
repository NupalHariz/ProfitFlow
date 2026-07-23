import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import CustomerController from '@/actions/App/Http/Controllers/Customers/CustomerController';
import CustomerForm from '@/components/customers/customer-form';
import DeleteCustomerDialog from '@/components/customers/delete-customer-dialog';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { CustomerFormData } from '@/types';

export default function EditCustomer({
    customer,
}: {
    customer: CustomerFormData;
}) {
    return (
        <>
            <Head title={`Edit ${customer.name}`} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <Button asChild variant="ghost" className="mb-2 -ml-3">
                            <Link href={CustomerController.show(customer.id)}>
                                <ArrowLeft />
                                {customer.name}
                            </Link>
                        </Button>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Edit Customer
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Older transactions show this live Customer
                            relationship, so profile edits may also appear
                            there.
                        </p>
                    </div>
                    <DeleteCustomerDialog
                        customerId={customer.id}
                        customerName={customer.name}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Customer details</CardTitle>
                        <CardDescription>
                            Updating contact data never recalculates or changes
                            transaction financial records.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <CustomerForm mode="edit" customer={customer} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

EditCustomer.layout = {
    breadcrumbs: [
        {
            title: 'Customers',
            href: CustomerController.index(),
        },
        {
            title: 'Edit',
            href: '#',
        },
    ],
};
