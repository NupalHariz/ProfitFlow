import { AlertTriangle, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
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
import { calculateItemPreview, formatRupiah } from '@/lib/money';
import type { MoneyPreview } from '@/lib/money';
import type {
    TransactionItemSnapshot,
    TransactionProductLookup,
} from '@/types';

export type EditableTransactionItem = {
    key: string;
    productId: string;
    quantity: string;
    unitSellingPrice: string;
    currentProductAvailable: boolean;
    snapshot?: TransactionItemSnapshot;
};

type Props = {
    index: number;
    item: EditableTransactionItem;
    products: TransactionProductLookup[];
    errors: Record<string, string | undefined>;
    canRemove: boolean;
    onChange: (item: EditableTransactionItem) => void;
    onRemove: () => void;
};

export default function TransactionItemEditor({
    index,
    item,
    products,
    errors,
    canRemove,
    onChange,
    onRemove,
}: Props) {
    const product = products.find(
        (candidate) => candidate.id.toString() === item.productId,
    );
    const preview = calculateItemPreview(
        product,
        item.quantity,
        item.unitSellingPrice,
    );
    const unavailable =
        item.snapshot !== undefined &&
        !item.currentProductAvailable &&
        item.productId === '';

    return (
        <Card>
            <CardHeader>
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <CardTitle>Item {index + 1}</CardTitle>
                        <CardDescription>
                            Choose an active product and optionally override its
                            selling price.
                        </CardDescription>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        disabled={!canRemove}
                        onClick={onRemove}
                    >
                        <Trash2 />
                        Remove
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="space-y-5">
                {unavailable && (
                    <div className="flex gap-3 rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-800 dark:text-amber-300">
                        <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                        <p>
                            The stored product is now inactive or deleted.
                            Select an available product before saving. The
                            historical snapshot remains visible below.
                        </p>
                    </div>
                )}

                <div className="grid gap-5 lg:grid-cols-2">
                    <div className="grid gap-2 lg:col-span-2">
                        <Label htmlFor={`items-${index}-product`}>
                            Product
                        </Label>
                        <input
                            type="hidden"
                            name={`items[${index}][product_id]`}
                            value={item.productId}
                        />
                        <Select
                            value={
                                item.productId === ''
                                    ? 'unselected'
                                    : item.productId
                            }
                            onValueChange={(value) =>
                                onChange({
                                    ...item,
                                    productId:
                                        value === 'unselected' ? '' : value,
                                    unitSellingPrice: '',
                                    currentProductAvailable:
                                        value === 'unselected'
                                            ? item.currentProductAvailable
                                            : true,
                                })
                            }
                        >
                            <SelectTrigger
                                id={`items-${index}-product`}
                                className="w-full"
                            >
                                <SelectValue placeholder="Select a product" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="unselected">
                                    Select a product
                                </SelectItem>
                                {products.map((option) => (
                                    <SelectItem
                                        key={option.id}
                                        value={option.id.toString()}
                                    >
                                        {option.name} · {option.sku} ·{' '}
                                        {option.category.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            message={
                                errors[`items.${index}.product_id`] ??
                                errors[`items.${index}`]
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`items-${index}-quantity`}>
                            Quantity
                        </Label>
                        <Input
                            id={`items-${index}-quantity`}
                            name={`items[${index}][quantity]`}
                            type="number"
                            min="1"
                            step="1"
                            inputMode="numeric"
                            value={item.quantity}
                            onChange={(event) =>
                                onChange({
                                    ...item,
                                    quantity: event.target.value,
                                })
                            }
                            required
                        />
                        <InputError
                            message={errors[`items.${index}.quantity`]}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`items-${index}-selling-price`}>
                            Selling-price override
                        </Label>
                        <Input
                            id={`items-${index}-selling-price`}
                            name={`items[${index}][unit_selling_price]`}
                            inputMode="decimal"
                            placeholder={
                                product
                                    ? `Default ${formatRupiah(product.default_selling_price)}`
                                    : 'Optional'
                            }
                            value={item.unitSellingPrice}
                            onChange={(event) =>
                                onChange({
                                    ...item,
                                    unitSellingPrice: event.target.value,
                                })
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            Leave blank to use the selected product's default
                            selling price.
                        </p>
                        <InputError
                            message={
                                errors[`items.${index}.unit_selling_price`]
                            }
                        />
                    </div>
                </div>

                {product && (
                    <div className="grid gap-3 rounded-lg border bg-muted/20 p-4 text-sm sm:grid-cols-3">
                        <ReferenceValue
                            label="Category"
                            value={product.category.name}
                        />
                        <ReferenceValue
                            label="Default selling price"
                            value={formatRupiah(product.default_selling_price)}
                        />
                        <ReferenceValue
                            label="Trusted production cost"
                            value={formatRupiah(
                                product.default_production_cost,
                            )}
                        />
                    </div>
                )}

                <ItemPreview preview={preview} />

                {item.snapshot && <StoredSnapshot snapshot={item.snapshot} />}
            </CardContent>
        </Card>
    );
}

function ItemPreview({ preview }: { preview: MoneyPreview }) {
    if (preview.state !== 'complete') {
        return (
            <p className="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
                {preview.state === 'invalid'
                    ? 'Preview unavailable: check the quantity and selling-price override.'
                    : 'Complete the product and quantity fields to preview this item.'}
            </p>
        );
    }

    return (
        <div className="grid gap-3 sm:grid-cols-3">
            <ReferenceValue
                label="Revenue preview"
                value={formatRupiah(preview.totalRevenue)}
            />
            <ReferenceValue
                label="Cost preview"
                value={formatRupiah(preview.totalProductionCost)}
            />
            <ReferenceValue
                label="Profit preview"
                value={formatRupiah(preview.netProfit)}
            />
        </div>
    );
}

function StoredSnapshot({ snapshot }: { snapshot: TransactionItemSnapshot }) {
    return (
        <div className="rounded-lg border border-dashed p-4">
            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                Stored draft snapshot
            </p>
            <p className="mt-2 font-medium">
                {snapshot.product_name_snapshot}{' '}
                <span className="font-mono text-xs text-muted-foreground">
                    {snapshot.product_sku_snapshot}
                </span>
            </p>
            <p className="mt-1 text-xs text-muted-foreground">
                {snapshot.category_name_snapshot ?? 'No category snapshot'} ·
                Selling {formatRupiah(snapshot.unit_selling_price)} · Cost{' '}
                {formatRupiah(snapshot.unit_production_cost)}
            </p>
        </div>
    );
}

function ReferenceValue({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 font-medium">{value}</p>
        </div>
    );
}
