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
                        The category will no longer be available for new
                        products or profit-sharing rules. It cannot be deleted
                        while products or scheme versions still use it. Existing
                        transactions remain unchanged.
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
                                        : 'Delete category'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
