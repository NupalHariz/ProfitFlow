import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import ProductCategoryController from '@/actions/App/Http/Controllers/ProductCategories/ProductCategoryController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type Props = {
    categoryId: number;
    categoryName: string;
};

export default function DeleteProductCategoryDialog({
    categoryId,
    categoryName,
}: Props) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="destructive" size="sm">
                    <Trash2 />
                    Delete
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete {categoryName}?</DialogTitle>
                    <DialogDescription>
                        This is a soft delete. The category will disappear from
                        normal master-data selection. Deletion is blocked while
                        any non-deleted product or profit-sharing version
                        references it; deactivate the category instead when
                        deletion is not allowed. Historical transactions are not
                        deleted.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...ProductCategoryController.destroy.form(categoryId)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <InputError message={errors.category} />
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Keep category
                                    </Button>
                                </DialogClose>
                                <Button
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    {processing
                                        ? 'Deleting…'
                                        : 'Soft delete category'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
