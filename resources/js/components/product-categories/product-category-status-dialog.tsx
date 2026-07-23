import { Form } from '@inertiajs/react';
import { CircleCheckBig, CircleOff } from 'lucide-react';
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
    isActive: boolean;
};

export default function ProductCategoryStatusDialog({
    categoryId,
    categoryName,
    isActive,
}: Props) {
    const activating = !isActive;

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    {activating ? <CircleCheckBig /> : <CircleOff />}
                    {activating ? 'Activate' : 'Deactivate'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {activating
                            ? `Activate ${categoryName}?`
                            : `Deactivate ${categoryName}?`}
                    </DialogTitle>
                    <DialogDescription>
                        {activating
                            ? 'Active products under this category can become available to new or resubmitted transaction drafts again.'
                            : 'Products under this category keep their own active/inactive state, but they cannot be selected for new or resubmitted transaction drafts while the category is inactive.'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...ProductCategoryController.updateStatus.form(categoryId)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="is_active"
                                value={activating ? '1' : '0'}
                            />
                            <InputError message={errors.category} />
                            <InputError message={errors.is_active} />
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button disabled={processing}>
                                    {processing
                                        ? activating
                                            ? 'Activating…'
                                            : 'Deactivating…'
                                        : activating
                                          ? 'Activate category'
                                          : 'Deactivate category'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
