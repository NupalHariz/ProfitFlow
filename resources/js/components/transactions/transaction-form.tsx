import { Form } from '@inertiajs/react';
import { CircleAlert, Plus } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import TransactionItemEditor from '@/components/transactions/transaction-item-editor';
import type { EditableTransactionItem } from '@/components/transactions/transaction-item-editor';
import TransactionSummary from '@/components/transactions/transaction-summary';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { aggregateMoneyPreviews, calculateItemPreview } from '@/lib/money';
import type {
    TransactionCustomerLookup,
    TransactionFormData,
    TransactionProductLookup,
} from '@/types';

type Props = {
    action: string;
    method: 'post';
    initialData: TransactionFormData;
    customers: TransactionCustomerLookup[];
    products: TransactionProductLookup[];
    lookupLimit: number;
    submitLabel: string;
};

export default function TransactionForm({
    action,
    method,
    initialData,
    customers,
    products,
    lookupLimit,
    submitLabel,
}: Props) {
    const initialRows = useMemo(() => editableRows(initialData), [initialData]);
    const [customerId, setCustomerId] = useState(
        initialData.current_customer_available === false
            ? ''
            : (initialData.customer_id?.toString() ?? ''),
    );
    const [transactionDate, setTransactionDate] = useState(
        initialData.transaction_date,
    );
    const [items, setItems] = useState(initialRows);
    const nextKey = useRef(initialRows.length);
    const initialSignature = useMemo(
        () =>
            formSignature(
                initialData.current_customer_available === false
                    ? ''
                    : (initialData.customer_id?.toString() ?? ''),
                initialData.transaction_date,
                initialRows,
            ),
        [initialData, initialRows],
    );
    const currentSignature = formSignature(customerId, transactionDate, items);
    const dirty = currentSignature !== initialSignature;
    const previews = items.map((item) =>
        calculateItemPreview(
            products.find(
                (product) => product.id.toString() === item.productId,
            ),
            item.quantity,
            item.unitSellingPrice,
        ),
    );
    const totalPreview = aggregateMoneyPreviews(previews);

    useEffect(() => {
        if (!dirty) {
            return;
        }

        const warnBeforeLeaving = (event: BeforeUnloadEvent) => {
            event.preventDefault();
        };

        window.addEventListener('beforeunload', warnBeforeLeaving);

        return () => {
            window.removeEventListener('beforeunload', warnBeforeLeaving);
        };
    }, [dirty]);

    function replaceItem(index: number, item: EditableTransactionItem): void {
        setItems((current) =>
            current.map((candidate, candidateIndex) =>
                candidateIndex === index ? item : candidate,
            ),
        );
    }

    function addItem(): void {
        const key = `new-${nextKey.current}`;
        nextKey.current += 1;
        setItems((current) => [
            ...current,
            {
                key,
                productId: '',
                quantity: '1',
                unitSellingPrice: '',
                currentProductAvailable: true,
            },
        ]);
    }

    return (
        <Form action={action} method={method} className="space-y-6">
            {({ processing, errors }) => (
                <>
                    <InputError message={errors.transaction} />
                    <InputError message={errors.items} />

                    <Card>
                        <CardHeader>
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <CardTitle>Transaction details</CardTitle>
                                    <CardDescription>
                                        Customer and transaction date apply to
                                        the complete draft.
                                    </CardDescription>
                                </div>
                                {dirty && (
                                    <span className="rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-xs font-medium text-amber-700 dark:text-amber-300">
                                        Unsaved changes
                                    </span>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent className="grid gap-5 md:grid-cols-2">
                            {initialData.current_customer_available ===
                                false && (
                                <div className="flex gap-3 rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-800 md:col-span-2 dark:text-amber-300">
                                    <CircleAlert className="mt-0.5 size-4 shrink-0" />
                                    <p>
                                        The stored customer is no longer
                                        available. Select another customer
                                        before saving.
                                    </p>
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="customer_id">Customer</Label>
                                <input
                                    type="hidden"
                                    name="customer_id"
                                    value={customerId}
                                />
                                <Select
                                    value={
                                        customerId === ''
                                            ? 'unselected'
                                            : customerId
                                    }
                                    onValueChange={(value) =>
                                        setCustomerId(
                                            value === 'unselected' ? '' : value,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="customer_id"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Select a customer" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="unselected">
                                            Select a customer
                                        </SelectItem>
                                        {customers.map((customer) => (
                                            <SelectItem
                                                key={customer.id}
                                                value={customer.id.toString()}
                                            >
                                                {customer.name}
                                                {customer.email
                                                    ? ` · ${customer.email}`
                                                    : ''}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.customer_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="transaction_date">
                                    Transaction date
                                </Label>
                                <Input
                                    id="transaction_date"
                                    name="transaction_date"
                                    type="date"
                                    value={transactionDate}
                                    onChange={(event) =>
                                        setTransactionDate(event.target.value)
                                    }
                                    required
                                />
                                <InputError message={errors.transaction_date} />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="space-y-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="font-semibold">
                                    Transaction items
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Row order is preserved. Duplicate products
                                    are allowed.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={addItem}
                            >
                                <Plus />
                                Add item
                            </Button>
                        </div>

                        {items.map((item, index) => (
                            <TransactionItemEditor
                                key={item.key}
                                index={index}
                                item={item}
                                products={products}
                                errors={errors}
                                canRemove={items.length > 1}
                                onChange={(updated) =>
                                    replaceItem(index, updated)
                                }
                                onRemove={() =>
                                    setItems((current) =>
                                        current.filter(
                                            (_, candidateIndex) =>
                                                candidateIndex !== index,
                                        ),
                                    )
                                }
                            />
                        ))}
                    </div>

                    <TransactionSummary preview={totalPreview} />

                    <div className="flex flex-col justify-between gap-3 border-t pt-5 sm:flex-row sm:items-center">
                        <p className="text-xs text-muted-foreground">
                            Lookups are currently bounded to {lookupLimit}{' '}
                            records. The backend recalculates every submitted
                            value before saving.
                        </p>
                        <Button disabled={processing}>
                            {processing ? 'Saving…' : submitLabel}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function editableRows(data: TransactionFormData): EditableTransactionItem[] {
    return data.items.map((item, index) => ({
        key: item.id === undefined ? `initial-${index}` : `stored-${item.id}`,
        productId:
            item.current_product_available === false
                ? ''
                : (item.product_id?.toString() ?? ''),
        quantity: item.quantity.toString(),
        unitSellingPrice: item.unit_selling_price,
        currentProductAvailable: item.current_product_available !== false,
        snapshot: item.snapshot,
    }));
}

function formSignature(
    customerId: string,
    transactionDate: string,
    items: EditableTransactionItem[],
): string {
    return JSON.stringify({
        customerId,
        transactionDate,
        items: items.map((item) => ({
            productId: item.productId,
            quantity: item.quantity,
            unitSellingPrice: item.unitSellingPrice,
        })),
    });
}
