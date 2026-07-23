import { Form } from '@inertiajs/react';
import { useState } from 'react';
import ProductCategoryController from '@/actions/App/Http/Controllers/ProductCategories/ProductCategoryController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { ProductCategoryFormData } from '@/types';

type Props =
    | {
          mode: 'create';
          category?: never;
      }
    | {
          mode: 'edit';
          category: ProductCategoryFormData;
      };

export default function ProductCategoryForm({ mode, category }: Props) {
    const [isActive, setIsActive] = useState(true);
    const form =
        mode === 'create'
            ? ProductCategoryController.store.form()
            : ProductCategoryController.update.form(category.id);

    return (
        <Form {...form} className="space-y-6">
            {({ processing, errors }) => (
                <>
                    <InputError message={errors.category} />

                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            defaultValue={category?.name ?? ''}
                            required
                            autoFocus
                            maxLength={120}
                            placeholder="Home & Office"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <Textarea
                            id="description"
                            name="description"
                            defaultValue={category?.description ?? ''}
                            placeholder="Describe the products grouped under this category."
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
                                        Active category
                                    </Label>
                                    <p className="text-xs text-muted-foreground">
                                        Active categories can make their active
                                        products available to transaction
                                        drafts.
                                    </p>
                                </div>
                            </div>
                            <InputError message={errors.is_active} />
                        </div>
                    )}

                    <p className="text-xs text-muted-foreground">
                        A stable slug is generated automatically when the
                        category is created. Renaming the category later will
                        not change it.
                    </p>

                    <div className="flex justify-end">
                        <Button disabled={processing}>
                            {processing
                                ? mode === 'create'
                                    ? 'Creating…'
                                    : 'Saving…'
                                : mode === 'create'
                                  ? 'Create category'
                                  : 'Save changes'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
