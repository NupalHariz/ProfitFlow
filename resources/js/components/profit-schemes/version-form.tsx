import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    ProfitSchemeCategoryLookup,
    ProfitSchemeVersionFormData,
} from '@/types';

type Props = {
    action: string;
    method: 'post';
    categories: ProfitSchemeCategoryLookup[];
    version?: ProfitSchemeVersionFormData;
    submitLabel: string;
    disabled?: boolean;
};

export default function VersionForm({
    action,
    method,
    categories,
    version,
    submitLabel,
    disabled = false,
}: Props) {
    const [categoryId, setCategoryId] = useState(
        version?.product_category_id?.toString() ?? 'general',
    );
    const [isActive, setIsActive] = useState(version?.is_active ?? true);

    return (
        <Form
            action={action}
            method={method}
            options={{ preserveScroll: true }}
            className="space-y-6"
        >
            {({ processing, errors }) => (
                <>
                    <InputError message={errors.version} />

                    <Card className="border-dashed bg-muted/20 shadow-none">
                        <CardContent className="text-sm text-muted-foreground">
                            General rules leave category empty. Empty profit
                            bounds are open-ended. Higher priority wins; equal
                            priorities use publication time, version, then ID.
                            New versions never rewrite historical transactions.
                        </CardContent>
                    </Card>

                    <div className="grid gap-5 md:grid-cols-2">
                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="product_category_id">
                                Product category
                            </Label>
                            <input
                                type="hidden"
                                name="product_category_id"
                                value={
                                    categoryId === 'general' ? '' : categoryId
                                }
                            />
                            <Select
                                value={categoryId}
                                onValueChange={setCategoryId}
                                disabled={disabled}
                            >
                                <SelectTrigger
                                    id="product_category_id"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="General" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="general">
                                        General — all categories
                                    </SelectItem>
                                    {categories.map((category) => (
                                        <SelectItem
                                            key={category.id}
                                            value={category.id.toString()}
                                        >
                                            {category.name}
                                            {!category.is_active
                                                ? ' (inactive)'
                                                : ''}
                                            {category.is_deleted
                                                ? ' (deleted)'
                                                : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.product_category_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="minimum_profit">
                                Minimum net profit
                            </Label>
                            <Input
                                id="minimum_profit"
                                name="minimum_profit"
                                inputMode="decimal"
                                placeholder="Open-ended"
                                defaultValue={version?.minimum_profit ?? ''}
                                disabled={disabled}
                            />
                            <InputError message={errors.minimum_profit} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="maximum_profit">
                                Maximum net profit
                            </Label>
                            <Input
                                id="maximum_profit"
                                name="maximum_profit"
                                inputMode="decimal"
                                placeholder="Open-ended"
                                defaultValue={version?.maximum_profit ?? ''}
                                disabled={disabled}
                            />
                            <InputError message={errors.maximum_profit} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="effective_from">
                                Effective from
                            </Label>
                            <Input
                                id="effective_from"
                                name="effective_from"
                                type="date"
                                required
                                defaultValue={
                                    version?.effective_from ??
                                    new Date().toISOString().slice(0, 10)
                                }
                                disabled={disabled}
                            />
                            <InputError message={errors.effective_from} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="effective_until">
                                Effective until
                            </Label>
                            <Input
                                id="effective_until"
                                name="effective_until"
                                type="date"
                                defaultValue={version?.effective_until ?? ''}
                                disabled={disabled}
                            />
                            <InputError message={errors.effective_until} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="priority">Priority</Label>
                            <Input
                                id="priority"
                                name="priority"
                                type="number"
                                step="1"
                                required
                                defaultValue={version?.priority ?? 0}
                                disabled={disabled}
                            />
                            <InputError message={errors.priority} />
                        </div>

                        <div className="flex items-center gap-3 self-end rounded-md border p-3">
                            <input
                                type="hidden"
                                name="is_active"
                                value={isActive ? '1' : '0'}
                            />
                            <Checkbox
                                id="is_active"
                                checked={isActive}
                                onCheckedChange={(checked) =>
                                    setIsActive(checked === true)
                                }
                                disabled={disabled}
                            />
                            <div className="grid gap-0.5">
                                <Label htmlFor="is_active">
                                    Active version
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    Only active versions can be published.
                                </p>
                            </div>
                            <InputError message={errors.is_active} />
                        </div>
                    </div>

                    {!disabled && (
                        <div className="flex justify-end">
                            <Button disabled={processing}>
                                {processing ? 'Saving…' : submitLabel}
                            </Button>
                        </div>
                    )}
                </>
            )}
        </Form>
    );
}
