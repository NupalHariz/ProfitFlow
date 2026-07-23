import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import TransactionController from '@/actions/App/Http/Controllers/Transactions/TransactionController';
import TransactionForm from '@/components/transactions/transaction-form';
import { Button } from '@/components/ui/button';
import type {
    TransactionCustomerLookup,
    TransactionFormData,
    TransactionProductLookup,
} from '@/types';

type PageProps = {
    transaction: TransactionFormData & {
        id: number;
        transaction_number: string;
    };
    customers: TransactionCustomerLookup[];
    products: TransactionProductLookup[];
    lookup_limit: number;
};

export default function EditTransaction({
    transaction,
    customers,
    products,
    lookup_limit,
}: PageProps) {
    const form = TransactionController.update.form(transaction.id);

    return (
        <>
            <Head title={`Edit ${transaction.transaction_number}`} />

            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <Button asChild variant="ghost" className="mb-2 -ml-3">
                        <Link href={TransactionController.show(transaction.id)}>
                            <ArrowLeft />
                            {transaction.transaction_number}
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Edit draft transaction
                    </h1>
                    <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                        Saving replaces the complete ordered draft. Stored
                        selling prices initialize as explicit values so the
                        current financial result remains unchanged unless you
                        edit or clear them.
                    </p>
                </div>

                <TransactionForm
                    action={form.action}
                    method={form.method}
                    initialData={transaction}
                    customers={customers}
                    products={products}
                    lookupLimit={lookup_limit}
                    submitLabel="Save complete draft"
                />
            </div>
        </>
    );
}

EditTransaction.layout = {
    breadcrumbs: [
        {
            title: 'Transactions',
            href: TransactionController.index(),
        },
    ],
};
