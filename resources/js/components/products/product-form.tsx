import { Form } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { useState } from 'react';
import ProductController from '@/actions/App/Http/Controllers/Products/ProductController';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
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
import { Textarea } from '@/components/ui/textarea';
import type { ProductCategoryOption, ProductFormData } from '@/types';

type Props =
    | {
          mode: 'create';
          product?: never;
          categories: ProductCategoryOption[];
          categoryOptionLimit: number;
      }
    | {
          mode: 'edit';
          product: ProductFormData;
          categories: ProductCategoryOption[];
          categoryOptionLimit: number;
      };

export default function ProductForm({
    mode,
    product,
    categories,
    categoryOptionLimit,
}: Props) {
    const [categoryId, setCategoryId] = useState(
        product
            ? String(product.product_category_id)
            : categories[0]
              ? String(categories[0].id)
              : '',
    );
    const [isActive, setIsActive] = useState(true);
    const form =
        mode === 'create'
            ? ProductController.store.form()
            : ProductController.update.form(product.id);
    const hasAvailableCategory = categories.length > 0;
    const currentCategoryInactive =
        mode === 'edit' && !product.category.is_active;

    return (
        <Form {...form} className="space-y-6">
            {({ processing, errors }) => (
                <>
                    <InputError message={errors.product} />

                    {!hasAvailableCategory && (
                        <Alert>
                            <AlertTriangle />
                            <AlertTitle>
                                No active product category is available
                            </AlertTitle>
                            <AlertDescription>
                                Create or activate a product category before
                                creating a product.
                            </AlertDescription>
                        </Alert>
                    )}

                    {currentCategoryInactive && (
                        <Alert>
                            <AlertTriangle />
                            <AlertTitle>
                                Current category is inactive
                            </AlertTitle>
                            <AlertDescription>
                                You may keep this category while editing other
                                fields. Reassigning the product requires an
                                active category, and activating the product is
                                blocked until its current category is active.
                            </AlertDescription>
                        </Alert>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="product_category_id">Category</Label>
                        <input
                            type="hidden"
                            name="product_category_id"
                            value={categoryId}
                        />
                        <Select
                            value={categoryId}
                            onValueChange={setCategoryId}
                            disabled={!hasAvailableCategory}
                        >
                            <SelectTrigger
                                id="product_category_id"
                                className="w-full"
                            >
                                <SelectValue placeholder="Select a category" />
                            </SelectTrigger>
                            <SelectContent>
                                {categories.map((category) => (
                                    <SelectItem
                                        key={category.id}
                                        value={String(category.id)}
                                    >
                                        {category.name}
                                        {!category.is_active
                                            ? ' (Inactive — current)'
                                            : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-xs text-muted-foreground">
                            Up to {categoryOptionLimit} categories are loaded.
                            The current category is always included on edit.
                        </p>
                        <InputError message={errors.product_category_id} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                name="name"
                                defaultValue={product?.name ?? ''}
                                required
                                autoFocus
                                maxLength={160}
                                placeholder="Professional plan"
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="sku">SKU</Label>
                            <Input
                                id="sku"
                                name="sku"
                                defaultValue={product?.sku ?? ''}
                                required
                                maxLength={80}
                                placeholder="PLAN-PRO-001"
                                className="font-mono"
                            />
                            <p className="text-xs text-muted-foreground">
                                SKUs are exact and case-sensitive. Deleted
                                product SKUs remain reserved.
                            </p>
                            <InputError message={errors.sku} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="default_selling_price">
                                Default selling price
                            </Label>
                            <Input
                                id="default_selling_price"
                                name="default_selling_price"
                                defaultValue={
                                    product?.default_selling_price ?? ''
                                }
                                required
                                inputMode="decimal"
                                autoComplete="off"
                                placeholder="150000.00"
                            />
                            <InputError
                                message={errors.default_selling_price}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="default_production_cost">
                                Default production cost
                            </Label>
                            <Input
                                id="default_production_cost"
                                name="default_production_cost"
                                defaultValue={
                                    product?.default_production_cost ?? ''
                                }
                                required
                                inputMode="decimal"
                                autoComplete="off"
                                placeholder="90000.00"
                            />
                            <InputError
                                message={errors.default_production_cost}
                            />
                        </div>
                    </div>

                    <p className="text-xs text-muted-foreground">
                        Enter non-negative decimal values with at most two
                        decimal places. These defaults affect only transaction
                        drafts saved or resubmitted later; existing transaction
                        snapshots never change.
                    </p>

                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <Textarea
                            id="description"
                            name="description"
                            defaultValue={product?.description ?? ''}
                            placeholder="Describe this product."
                            rows={5}
                        />
                        <InputError message={errors.description} />
                    </div>

                    {mode === 'create' && (
                        <div className="space-y-3 rounded-md border p-4">
                            <input
                                type="hidden"
                                name="is_active"
                                value={isActive ? '1' : '0'}
                            />
                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id="is_active"
                                    checked={isActive}
                                    onCheckedChange={(checked) =>
                                        setIsActive(checked === true)
                                    }
                                />
                                <div className="grid gap-1">
                                    <Label htmlFor="is_active">
                                        Active product
                                    </Label>
                                    <p className="text-xs text-muted-foreground">
                                        Selection in transaction drafts also
                                        requires the category to be active.
                                    </p>
                                </div>
                            </div>
                            <InputError message={errors.is_active} />
                        </div>
                    )}

                    <div className="flex justify-end">
                        <Button
                            disabled={
                                processing ||
                                !hasAvailableCategory ||
                                categoryId === ''
                            }
                        >
                            {processing
                                ? mode === 'create'
                                    ? 'Creating…'
                                    : 'Saving…'
                                : mode === 'create'
                                  ? 'Create product'
                                  : 'Save changes'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
