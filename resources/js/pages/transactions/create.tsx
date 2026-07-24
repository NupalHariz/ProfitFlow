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
    customers: TransactionCustomerLookup[];
    products: TransactionProductLookup[];
    lookup_limit: number;
};

export default function CreateTransaction({
    customers,
    products,
    lookup_limit,
}: PageProps) {
    const form = TransactionController.store.form();
    const initialData: TransactionFormData = {
        customer_id: null,
        transaction_date: localDateInputValue(new Date()),
        items: [
            {
                product_id: null,
                quantity: 1,
                unit_selling_price: '',
            },
        ],
    };

    return (
        <>
            <Head title="Create transaction" />

            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <Button asChild variant="ghost" className="mb-2 -ml-3">
                        <Link href={TransactionController.index()}>
                            <ArrowLeft />
                            Transactions
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Create draft transaction
                    </h1>
                    <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                        Add the Customer, date, and items for this transaction.
                    </p>
                </div>

                <TransactionForm
                    action={form.action}
                    method={form.method}
                    initialData={initialData}
                    customers={customers}
                    products={products}
                    lookupLimit={lookup_limit}
                    submitLabel="Create draft transaction"
                />
            </div>
        </>
    );
}

CreateTransaction.layout = {
    breadcrumbs: [
        {
            title: 'Transactions',
            href: TransactionController.index(),
        },
    ],
};

function localDateInputValue(date: Date): string {
    const year = date.getFullYear().toString();
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const day = date.getDate().toString().padStart(2, '0');

    return `${year}-${month}-${day}`;
}
